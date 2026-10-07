<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('paiements')) {
            return;
        }

        $missingCodePromoId = !Schema::hasColumn('paiements', 'code_promo_id');
        $missingMontantInitial = !Schema::hasColumn('paiements', 'montant_initial');
        $missingMontantReduction = !Schema::hasColumn('paiements', 'montant_reduction');
        $missingMontantFinal = !Schema::hasColumn('paiements', 'montant_final');

        if (!$missingCodePromoId && !$missingMontantInitial && !$missingMontantReduction && !$missingMontantFinal) {
            return;
        }

        Schema::table('paiements', function (Blueprint $table) use (
            $missingCodePromoId,
            $missingMontantInitial,
            $missingMontantReduction,
            $missingMontantFinal
        ) {
            if ($missingCodePromoId) {
                $table->unsignedBigInteger('code_promo_id')->nullable()->after('forfait_id');
            }

            if ($missingMontantInitial) {
                $table->decimal('montant_initial', 12, 2)->nullable()->after('amount');
            }

            if ($missingMontantReduction) {
                $table->decimal('montant_reduction', 12, 2)->nullable()->after('montant_initial');
            }

            if ($missingMontantFinal) {
                $table->decimal('montant_final', 12, 2)->nullable()->after('montant_reduction');
            }
        });
    }

    public function down(): void
    {
        // Migration corrective: ne pas supprimer des colonnes pouvant contenir des paiements en production.
    }
};
