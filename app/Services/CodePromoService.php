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
        $montantInitial = round(max(0, (float) $forfait->prix), 2);
        $montantApresReductionForfait = $this->forfaitDiscountedAmount($forfait, $montantInitial);
        $montantReductionForfait = round($montantInitial - $montantApresReductionForfait, 2);

        if (empty($code)) {
            return [
                'code_promo' => null,
                'montant_initial' => $montantInitial,
                'montant_reduction_forfait' => $montantReductionForfait,
                'montant_apres_reduction_forfait' => $montantApresReductionForfait,
                'montant_reduction_code_promo' => 0,
                'montant_reduction' => $montantReductionForfait,
                'montant_final' => $montantApresReductionForfait,
            ];
        }

        $codePromo = $this->getValidCode($code, $forfait, $userId);
        $montantReductionCodePromo = round(
            ($montantApresReductionForfait * (float) $codePromo->pourcentage) / 100,
            2
        );
        $montantReductionCodePromo = min($montantReductionCodePromo, $montantApresReductionForfait);
        $montantFinal = round(max(0, $montantApresReductionForfait - $montantReductionCodePromo), 2);
        $montantReduction = round($montantInitial - $montantFinal, 2);

        return [
            'code_promo' => $codePromo,
            'montant_initial' => $montantInitial,
            'montant_reduction_forfait' => $montantReductionForfait,
            'montant_apres_reduction_forfait' => $montantApresReductionForfait,
            'montant_reduction_code_promo' => $montantReductionCodePromo,
            'montant_reduction' => $montantReduction,
            'montant_final' => $montantFinal,
        ];
    }

    private function forfaitDiscountedAmount(Forfait_usager $forfait, float $montantInitial): float
    {
        $reduction = max(0, (float) $forfait->reduction);

        if ($reduction <= 0 || $montantInitial <= 0) {
            return $montantInitial;
        }

        $type = Str::lower(trim((string) $forfait->reduction_type));

        if ($type === 'percentage') {
            $montantCalcule = $montantInitial - (($montantInitial * min($reduction, 100)) / 100);
        } elseif ($type === 'fixed') {
            $montantCalcule = $montantInitial - min($reduction, $montantInitial);
        } else {
            $montantCalcule = $montantInitial;
        }

        $montantConfigure = $forfait->montant_apres_reduction;
        if ($montantConfigure !== null) {
            $montantConfigure = (float) $montantConfigure;

            if ($montantConfigure > 0 && $montantConfigure <= $montantInitial) {
                return round($montantConfigure, 2);
            }
        }

        return round(max(0, $montantCalcule), 2);
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
