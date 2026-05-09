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
        if (!Schema::hasTable('ss_categorie_services')) {
            Schema::create('ss_categorie_services', function (Blueprint $table) {
                $table->id();
                $table->string('libelle')->unique();
                $table->string('image')->nullable();
                $table->boolean('statut')->default(true);
                $table->boolean('is_pro')->default(false);
                $table->tinyInteger('pro_or_usager')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('sous_categorie_services', 'ss_categorie_service_id')) {
            Schema::table('sous_categorie_services', function (Blueprint $table) {
                $table->unsignedBigInteger('ss_categorie_service_id')->nullable()->after('pro_or_usager');

                $table->foreign('ss_categorie_service_id')
                    ->references('id')
                    ->on('ss_categorie_services')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sous_categorie_services', 'ss_categorie_service_id')) {
            Schema::table('sous_categorie_services', function (Blueprint $table) {
                $table->dropForeign(['ss_categorie_service_id']);
                $table->dropColumn('ss_categorie_service_id');
            });
        }

        Schema::dropIfExists('ss_categorie_services');
    }
};
