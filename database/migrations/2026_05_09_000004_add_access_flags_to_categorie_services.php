<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categorie_services', function (Blueprint $table) {
            if (!Schema::hasColumn('categorie_services', 'accessible_abonnement_expire')) {
                $table->boolean('accessible_abonnement_expire')->default(false)->after('sous_categorie_service_id');
            }

            if (!Schema::hasColumn('categorie_services', 'visible_par_defaut')) {
                $table->boolean('visible_par_defaut')->default(false)->after('accessible_abonnement_expire');
            }

            if (!Schema::hasColumn('categorie_services', 'accessible_en_fonction_de_mon_abonnement_actif')) {
                $table->boolean('accessible_en_fonction_de_mon_abonnement_actif')->default(true)->after('visible_par_defaut');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categorie_services', function (Blueprint $table) {
            if (Schema::hasColumn('categorie_services', 'accessible_en_fonction_de_mon_abonnement_actif')) {
                $table->dropColumn('accessible_en_fonction_de_mon_abonnement_actif');
            }

            if (Schema::hasColumn('categorie_services', 'visible_par_defaut')) {
                $table->dropColumn('visible_par_defaut');
            }

            if (Schema::hasColumn('categorie_services', 'accessible_abonnement_expire')) {
                $table->dropColumn('accessible_abonnement_expire');
            }
        });
    }
};
