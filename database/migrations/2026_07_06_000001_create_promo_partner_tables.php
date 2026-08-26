<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('partenaires_promo')) {
            Schema::create('partenaires_promo', function (Blueprint $table) {
                $table->id();
                $table->string('nom');
                $table->string('email')->nullable();
                $table->string('telephone')->nullable();
                $table->text('adresse')->nullable();
                $table->boolean('statut')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index('statut');
            });
        }

        if (!Schema::hasTable('codes_promo')) {
            Schema::create('codes_promo', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('partenaire_promo_id');
                $table->unsignedBigInteger('forfait_usager_id')->nullable();
                $table->string('code', 30)->unique();
                $table->decimal('pourcentage', 5, 2);
                $table->date('date_debut')->nullable();
                $table->date('date_fin')->nullable();
                $table->unsignedInteger('usage_limit')->nullable();
                $table->unsignedInteger('usage_count')->default(0);
                $table->boolean('is_unlimited')->default(false);
                $table->boolean('one_use_per_user')->default(true);
                $table->boolean('statut')->default(true);
                $table->timestamps();

                $table->foreign('partenaire_promo_id')->references('id')->on('partenaires_promo')->cascadeOnDelete();
                $table->foreign('forfait_usager_id')->references('id')->on('forfait_usagers')->nullOnDelete();
                $table->index(['statut', 'date_debut', 'date_fin']);
            });
        }

        if (!Schema::hasTable('code_promo_utilisations')) {
            Schema::create('code_promo_utilisations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('code_promo_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('abonnement_usager_id')->nullable();
                $table->unsignedBigInteger('forfait_usager_id')->nullable();
                $table->unsignedBigInteger('paiement_id')->nullable();
                $table->decimal('montant_initial', 12, 2)->default(0);
                $table->decimal('montant_reduction', 12, 2)->default(0);
                $table->decimal('montant_final', 12, 2)->default(0);
                $table->timestamps();

                $table->foreign('code_promo_id')->references('id')->on('codes_promo')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('abonnement_usager_id')->references('id')->on('abonnement_usagers')->nullOnDelete();
                $table->foreign('forfait_usager_id')->references('id')->on('forfait_usagers')->nullOnDelete();
                $table->index(['code_promo_id', 'user_id']);
            });
        }

        if (Schema::hasTable('paiements')) {
            Schema::table('paiements', function (Blueprint $table) {
                if (!Schema::hasColumn('paiements', 'code_promo_id')) {
                    $table->unsignedBigInteger('code_promo_id')->nullable()->after('forfait_id');
                }

                if (!Schema::hasColumn('paiements', 'montant_initial')) {
                    $table->decimal('montant_initial', 12, 2)->nullable()->after('amount');
                }

                if (!Schema::hasColumn('paiements', 'montant_reduction')) {
                    $table->decimal('montant_reduction', 12, 2)->nullable()->after('montant_initial');
                }

                if (!Schema::hasColumn('paiements', 'montant_final')) {
                    $table->decimal('montant_final', 12, 2)->nullable()->after('montant_reduction');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('paiements')) {
            Schema::table('paiements', function (Blueprint $table) {
                foreach (['code_promo_id', 'montant_initial', 'montant_reduction', 'montant_final'] as $column) {
                    if (Schema::hasColumn('paiements', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('code_promo_utilisations');
        Schema::dropIfExists('codes_promo');
        Schema::dropIfExists('partenaires_promo');
    }
};
