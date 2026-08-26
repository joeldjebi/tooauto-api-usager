<?php

namespace App\Http\Controllers;

use App\Models\Forfait_usager;
use App\Services\CodePromoService;
use Illuminate\Http\Request;
use RuntimeException;

class CodePromoController extends Controller
{
    public function verifier(Request $request, CodePromoService $codePromoService)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30',
            'forfait_id' => 'required|integer|exists:forfait_usagers,id',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $userId = (int) ($validated['user_id'] ?? optional($request->user())->id);
        if ($userId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.',
            ], 401);
        }

        try {
            $forfait = Forfait_usager::findOrFail($validated['forfait_id']);
            $quote = $codePromoService->quote($validated['code'], $forfait, $userId);
            $codePromo = $quote['code_promo'];

            return response()->json([
                'success' => true,
                'message' => 'Code promo valide.',
                'data' => [
                    'code' => $codePromo->code,
                    'pourcentage' => (float) $codePromo->pourcentage,
                    'partenaire' => optional($codePromo->partenaire)->nom,
                    'forfait_id' => $forfait->id,
                    'montant_initial' => $quote['montant_initial'],
                    'montant_reduction' => $quote['montant_reduction'],
                    'montant_final' => $quote['montant_final'],
                ],
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
