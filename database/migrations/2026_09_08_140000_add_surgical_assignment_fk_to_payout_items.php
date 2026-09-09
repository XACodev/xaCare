<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la foreign key de payout_items a surgical_assignments.
     *
     * Se hace en una migración separada (posterior a la creación de
     * surgical_assignments y a la migración de datos legacy) para evitar que
     * entornos con datos reales fallen al correr
     * 2026_09_02_090500_generalize_payout_batches_and_items.php, que renombra
     * la columna antes de que las asignaciones existan.
     */
    public function up(): void
    {
        $existingForeign = collect(Schema::getForeignKeys('payout_items'))
            ->first(fn ($fk) => in_array('surgical_assignment_id', $fk['columns'] ?? [], true));

        if ($existingForeign !== null) {
            return;
        }

        Schema::table('payout_items', function (Blueprint $table) {
            $table->foreign('surgical_assignment_id')
                ->references('id')
                ->on('surgical_assignments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payout_items', function (Blueprint $table) {
            $table->dropForeign(['surgical_assignment_id']);
        });
    }
};
