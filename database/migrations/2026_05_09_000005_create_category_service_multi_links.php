<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('categorie_service_sous_categorie_service')) {
            Schema::create('categorie_service_sous_categorie_service', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('categorie_service_id');
                $table->unsignedBigInteger('sous_categorie_service_id');
                $table->timestamps();

                $table->unique(
                    ['categorie_service_id', 'sous_categorie_service_id'],
                    'cat_sous_cat_unique'
                );
                $table->foreign('categorie_service_id', 'cat_sous_cat_cat_fk')
                    ->references('id')
                    ->on('categorie_services')
                    ->cascadeOnDelete();
                $table->foreign('sous_categorie_service_id', 'cat_sous_cat_sous_fk')
                    ->references('id')
                    ->on('sous_categorie_services')
                    ->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('sous_categorie_service_ss_categorie_service')) {
            Schema::create('sous_categorie_service_ss_categorie_service', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sous_categorie_service_id');
                $table->unsignedBigInteger('ss_categorie_service_id');
                $table->timestamps();

                $table->unique(
                    ['sous_categorie_service_id', 'ss_categorie_service_id'],
                    'sous_ss_cat_unique'
                );
                $table->foreign('sous_categorie_service_id', 'sous_ss_cat_sous_fk')
                    ->references('id')
                    ->on('sous_categorie_services')
                    ->cascadeOnDelete();
                $table->foreign('ss_categorie_service_id', 'sous_ss_cat_ss_fk')
                    ->references('id')
                    ->on('ss_categorie_services')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('categorie_services', 'sous_categorie_service_id')) {
            DB::statement("
                INSERT IGNORE INTO categorie_service_sous_categorie_service
                    (categorie_service_id, sous_categorie_service_id, created_at, updated_at)
                SELECT id, sous_categorie_service_id, NOW(), NOW()
                FROM categorie_services
                WHERE sous_categorie_service_id IS NOT NULL
            ");
        }

        if (Schema::hasColumn('sous_categorie_services', 'ss_categorie_service_id')) {
            DB::statement("
                INSERT IGNORE INTO sous_categorie_service_ss_categorie_service
                    (sous_categorie_service_id, ss_categorie_service_id, created_at, updated_at)
                SELECT id, ss_categorie_service_id, NOW(), NOW()
                FROM sous_categorie_services
                WHERE ss_categorie_service_id IS NOT NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sous_categorie_service_ss_categorie_service');
        Schema::dropIfExists('categorie_service_sous_categorie_service');
    }
};

