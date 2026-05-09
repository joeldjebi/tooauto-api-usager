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
        if (!Schema::hasColumn('forfait_usagers', 'nombre_vehicule')) {
            Schema::table('forfait_usagers', function (Blueprint $table) {
                $table->unsignedInteger('nombre_vehicule')->default(0)->after('prix');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('forfait_usagers', 'nombre_vehicule')) {
            Schema::table('forfait_usagers', function (Blueprint $table) {
                $table->dropColumn('nombre_vehicule');
            });
        }
    }
};
