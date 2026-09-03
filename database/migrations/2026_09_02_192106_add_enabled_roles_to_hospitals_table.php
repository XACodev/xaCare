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
        Schema::table('hospitals', function (Blueprint $table) {
            // null = solo ve los roles "core" (admin/doctor/instrumentist/circulating,
            // ver Hospital::CORE_ROLES) — cualquier rol nuevo del catálogo global nace
            // invisible para todos los hospitales hasta que el super admin lo habilite
            // explícitamente para uno. Evita que "abrir un rol para uno" lo abra para todos.
            $table->json('enabled_roles')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->dropColumn('enabled_roles');
        });
    }
};
