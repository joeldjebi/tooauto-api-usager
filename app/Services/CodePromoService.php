<?php

namespace App\Services;

use App\Models\CodePromo;
use App\Models\CodePromoUtilisation;
use App\Models\Forfait_usager;
use App\Models\Paiement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CodePromoService
{
    public function quote(?string $code, Forfait_usager $forfait, int $userId): array
    {
        $montantInitial = (float) $forfait->prix;

        if (empty($code)) {
            return [
                'code_promo' => null,
                'montant_initial' => $montantInitial,
                'montant_reduction' => 0,
                'montant_final' => $montantInitial,
            ];
        }

        $codePromo = $this->getValidCode($code, $forfait, $userId);
        $montantReduction = round(($montantInitial * (float) $codePromo->pourcentage) / 100, 2);
        $montantFinal = max(0, $montantInitial - $montantReduction);

        return [
            'code_promo' => $codePromo,
            'montant_initial' => $montantInitial,
            'montant_reduction' => $montantReduction,
            'montant_final' => $montantFinal,
        ];
    }

    public function getValidCode(string $code, Forfait_usager $forfait, int $userId): CodePromo
    {
        $normalizedCode = Str::upper(trim($code));

        $codePromo = CodePromo::with('partenaire')
            ->where('code', $normalizedCode)
            ->first();

        if (!$codePromo) {
            throw new RuntimeException('Code promo introuvable.');
        }

        if ((int) $codePromo->statut !== 1 || !$codePromo->partenaire || (int) $codePromo->partenaire->statut !== 1) {
            throw new RuntimeException('Code promo inactif.');
        }

        $today = Carbon::today();
        if ($codePromo->date_debut && $today->lt($codePromo->date_debut)) {
            throw new RuntimeException('Code promo pas encore actif.');
        }

        if ($codePromo->date_fin && $today->gt($codePromo->date_fin)) {
            throw new RuntimeException('Code promo expiré.');
        }

        if ($codePromo->forfait_usager_id && (int) $codePromo->forfait_usager_id !== (int) $forfait->id) {
            throw new RuntimeException('Code promo non applicable à ce forfait.');
        }

        if (!$codePromo->is_unlimited && $codePromo->usage_limit !== null && (int) $codePromo->usage_count >= (int) $codePromo->usage_limit) {
            throw new RuntimeException('Le nombre maximal d’utilisations de ce code est atteint.');
        }

        if ($codePromo->one_use_per_user && CodePromoUtilisation::where('code_promo_id', $codePromo->id)->where('user_id', $userId)->exists()) {
            throw new RuntimeException('Vous avez déjà utilisé ce code promo.');
        }

        return $codePromo;
    }

    public function recordUtilisation(Paiement $paiement, $abonnement): ?CodePromoUtilisation
    {
        if (empty($paiement->code_promo_id)) {
            return null;
        }

        return DB::transaction(function () use ($paiement, $abonnement) {
            $existing = CodePromoUtilisation::where('paiement_id', $paiement->id)->first();
            if ($existing) {
                return $existing;
            }

            $codePromo = CodePromo::lockForUpdate()->find($paiement->code_promo_id);
            if (!$codePromo) {
                return null;
            }

            $utilisation = CodePromoUtilisation::create([
                'code_promo_id' => $codePromo->id,
                'user_id' => $paiement->user_id,
                'abonnement_usager_id' => $abonnement->id ?? null,
                'forfait_usager_id' => $paiement->forfait_id,
                'paiement_id' => $paiement->id,
                'montant_initial' => $paiement->montant_initial ?? $paiement->amount,
                'montant_reduction' => $paiement->montant_reduction ?? 0,
                'montant_final' => $paiement->montant_final ?? $paiement->amount,
            ]);

            $codePromo->increment('usage_count');

            return $utilisation;
        });
    }
}
