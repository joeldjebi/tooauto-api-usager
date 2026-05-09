<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Validator;

class NotificationController extends Controller
{
    protected $firebaseService;

    public function __construct()
    {
        $this->firebaseService = new FirebaseNotificationService();
    }

    /**
     * Envoyer une notification à un utilisateur spécifique
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendToUser(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = User::find($request->user_id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable.',
                ], 404);
            }

            if (!$user->fcm_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'utilisateur n\'a pas de token FCM enregistré.',
                ], 400);
            }

            $data = $request->data ?? [];
            $result = $this->firebaseService->sendToUser(
                $user,
                $request->title,
                $request->body,
                $data
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Notification envoyée avec succès.',
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Échec de l\'envoi de la notification.',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi de la notification.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Envoyer une notification à plusieurs utilisateurs
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendToMultipleUsers(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userIds = $request->user_ids;
            $data = $request->data ?? [];
            
            $result = $this->firebaseService->sendToMultipleUsers(
                $userIds,
                $request->title,
                $request->body,
                $data
            );

            if ($result) {
                // Compter le nombre d'utilisateurs avec token FCM
                $usersWithToken = User::whereIn('id', $userIds)
                    ->whereNotNull('fcm_token')
                    ->count();

                return response()->json([
                    'success' => true,
                    'message' => "Notification envoyée à {$usersWithToken} utilisateur(s).",
                    'users_notified' => $usersWithToken,
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Échec de l\'envoi de la notification.',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi de la notification.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Envoyer une notification à tous les utilisateurs
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendToAllUsers(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $request->data ?? [];
            
            $result = $this->firebaseService->sendToAllUsers(
                $request->title,
                $request->body,
                $data
            );

            if ($result) {
                // Compter le nombre total d'utilisateurs avec token FCM
                $totalUsers = User::whereNotNull('fcm_token')->count();

                return response()->json([
                    'success' => true,
                    'message' => "Notification envoyée à {$totalUsers} utilisateur(s).",
                    'users_notified' => $totalUsers,
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun utilisateur avec token FCM trouvé.',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi de la notification.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Envoyer une notification à l'utilisateur connecté
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendToCurrentUser(Request $request)
    {
        // Validation des données d'entrée
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié.',
                ], 401);
            }

            if (!$user->fcm_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas de token FCM enregistré.',
                ], 400);
            }

            $data = $request->data ?? [];
            $result = $this->firebaseService->sendToUser(
                $user,
                $request->title,
                $request->body,
                $data
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Notification envoyée avec succès.',
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Échec de l\'envoi de la notification.',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi de la notification.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Déclencher l'envoi des notifications d'expiration d'alertes
     * Cette méthode est appelée par le service de cron externe
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function triggerAlertNotifications()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('alerts:send-expiration-notifications');
            
            $output = \Illuminate\Support\Facades\Artisan::output();
            
            return response()->json([
                'success' => true,
                'message' => 'Notifications d\'expiration d\'alertes envoyées avec succès.',
                'output' => $output,
                'timestamp' => now()->toDateTimeString(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi des notifications.',
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString(),
            ], 500);
        }
    }

    /**
     * Consulter les logs des notifications envoyées
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNotificationLogs(Request $request)
    {
        try {
            $logFile = storage_path('logs/laravel.log');
            
            if (!file_exists($logFile)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fichier de logs introuvable.',
                ], 404);
            }

            // Lire les dernières lignes du fichier de log
            $lines = $request->input('lines', 100); // Par défaut 100 lignes
            $lines = min($lines, 1000); // Maximum 1000 lignes
            
            $command = "tail -n {$lines} " . escapeshellarg($logFile);
            $logContent = shell_exec($command);
            
            // Filtrer les logs liés aux notifications
            $notificationLogs = [];
            $allLines = explode("\n", $logContent);
            
            foreach ($allLines as $line) {
                if (stripos($line, 'Notification FCM') !== false || 
                    stripos($line, 'notification d\'expiration') !== false ||
                    stripos($line, 'Début de l\'envoi des notifications') !== false ||
                    stripos($line, 'Fin de l\'envoi des notifications') !== false) {
                    $notificationLogs[] = $line;
                }
            }
            
            // Si un filtre de date est fourni
            $dateFilter = $request->input('date');
            if ($dateFilter) {
                $filteredLogs = [];
                foreach ($notificationLogs as $log) {
                    if (stripos($log, $dateFilter) !== false) {
                        $filteredLogs[] = $log;
                    }
                }
                $notificationLogs = $filteredLogs;
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Logs récupérés avec succès.',
                'total_lines' => count($notificationLogs),
                'logs' => array_reverse($notificationLogs), // Plus récents en premier
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des logs.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
