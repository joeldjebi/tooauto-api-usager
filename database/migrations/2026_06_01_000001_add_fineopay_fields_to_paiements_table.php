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

        Schema::table('paiements', function (Blueprint $table) {
            if (!Schema::hasColumn('paiements', 'fineopay_reference')) {
                $table->string('fineopay_reference')->nullable();
            }

            if (!Schema::hasColumn('paiements', 'checkout_link')) {
                $table->text('checkout_link')->nullable();
            }

            if (!Schema::hasColumn('paiements', 'date_debut')) {
                $table->dateTime('date_debut')->nullable();
            }

            if (!Schema::hasColumn('paiements', 'date_fin')) {
                $table->dateTime('date_fin')->nullable();
            }

            if (!Schema::hasColumn('paiements', 'reponse_api')) {
                $table->json('reponse_api')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('paiements')) {
            return;
        }

        Schema::table('paiements', function (Blueprint $table) {
            foreach (['fineopay_reference', 'checkout_link', 'date_debut', 'date_fin', 'reponse_api'] as $column) {
                if (Schema::hasColumn('paiements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
