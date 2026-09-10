<?php
// database/migrations/2026_09_10_100200_drop_procedure_type_from_role_rates_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_rates', function (Blueprint $table) {
            $table->dropColumn('procedure_type');
        });
    }

    public function down(): void
    {
        Schema::table('role_rates', function (Blueprint $table) {
            $table->string('procedure_type')->nullable()->after('user_id');
        });
        // No repuebla el string desde procedure_type_id: revertir aquí solo restaura la
        // columna vacía. El backfill de la migración anterior ya movió el dato real a
        // procedure_types; no hay pérdida porque ProcedureType sigue existiendo.
    }
};
