<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private FirebaseNotificationService $firebaseService)
    {
    }

    public function sendToUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'data' => ['nullable', 'array'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        if (!$user->fcm_token) {
            return response()->json([
                'success' => false,
                'message' => "L'utilisateur n'a pas de token FCM enregistre.",
            ], 422);
        }

        return $this->reportResponse(
            $this->firebaseService->sendToUser(
                $user,
                $validated['title'],
                $validated['body'],
                $validated['data'] ?? []
            )
        );
    }

    public function sendToMultipleUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'data' => ['nullable', 'array'],
        ]);

        return $this->reportResponse(
            $this->firebaseService->sendToMultipleUsers(
                $validated['user_ids'],
                $validated['title'],
                $validated['body'],
                $validated['data'] ?? []
            )
        );
    }

    public function sendToAllUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'data' => ['nullable', 'array'],
        ]);

        return $this->reportResponse(
            $this->firebaseService->sendToAllUsers(
                $validated['title'],
                $validated['body'],
                $validated['data'] ?? []
            )
        );
    }

    public function sendToCurrentUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'data' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifie.',
            ], 401);
        }

        if (!$user->fcm_token) {
            return response()->json([
                'success' => false,
                'message' => "L'utilisateur n'a pas de token FCM enregistre.",
            ], 422);
        }

        return $this->reportResponse(
            $this->firebaseService->sendToUser(
                $user,
                $validated['title'],
                $validated['body'],
                $validated['data'] ?? []
            )
        );
    }

    private function reportResponse(array $report): JsonResponse
    {
        if ($report['target_count'] === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun utilisateur avec token FCM trouve.',
                'data' => $report,
            ], 404);
        }

        $success = $report['success_count'] > 0;

        return response()->json([
            'success' => $success,
            'message' => $success
                ? 'Notification envoyee avec succes.'
                : "Echec de l'envoi de la notification.",
            'data' => $report,
        ], $success ? 200 : 502);
    }
}
