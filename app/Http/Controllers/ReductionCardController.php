<?php

namespace App\Http\Controllers;

use App\Services\ReductionCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ReductionCardController extends Controller
{
    public function index(Request $request, ReductionCardService $reductionCardService)
    {
        $user = $request->user() ?: Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Liste des cartes de réduction.',
            'data' => $reductionCardService->listActiveUserCards((int) $user->id),
        ]);
    }

    public function verifier(Request $request, ReductionCardService $reductionCardService)
    {
        $validated = $request->validate([
            'card_code' => 'nullable|string|max:100',
            'qr_code' => 'nullable|string|max:150',
        ]);

        $user = $request->user() ?: Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.',
            ], 401);
        }

        try {
            return response()->json([
                'success' => true,
                'message' => 'Carte de réduction valide.',
                'data' => $reductionCardService->verifyUserCard(
                    (int) $user->id,
                    $validated['card_code'] ?? null,
                    $validated['qr_code'] ?? null
                ),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function appliquer(Request $request, ReductionCardService $reductionCardService)
    {
        $validated = $request->validate([
            'card_code' => 'nullable|string|max:100',
            'qr_code' => 'nullable|string|max:150',
            'montant_initial' => 'required|numeric|min:0',
            'establishment_type' => 'required|string|in:etablissement,lavage,station',
            'establishment_id' => 'required|integer',
            'applied_by_id' => 'required|integer',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user() ?: Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.',
            ], 401);
        }

        try {
            return response()->json([
                'success' => true,
                'message' => 'Réduction appliquée avec succès.',
                'data' => $reductionCardService->applyDiscount((int) $user->id, $validated),
            ], 201);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
