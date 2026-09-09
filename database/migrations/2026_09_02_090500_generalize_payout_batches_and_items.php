<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generaliza el schema de payouts para soportar cualquier rol pagado.
     *
     * NOTA: la FK a surgical_assignments NO se agrega aquí. En entornos con datos
     * legacy, esta migración corre ANTES de que los SurgicalAssignment existan,
     * por lo que agregar la FK en este momento haría fallar el deploy. La FK se
     * agrega posteriormente en 2026_09_08_140000_add_surgical_assignment_fk_to_payout_items.php,
     * después de que la migración 2026_09_02_110000 haya creado las asignaciones y
     * actualizado los payout_items para que apunten a IDs válidos.
     */
    public function up(): void
    {
        Schema::table('payout_batches', function (Blueprint $table) {
            $table->renameColumn('instrumentist_id', 'payee_id');
        });

        Schema::table('payout_items', function (Blueprint $table) {
            $table->dropForeign(['procedure_id']);
            $table->renameColumn('procedure_id', 'surgical_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::table('payout_items', function (Blueprint $table) {
            $table->renameColumn('surgical_assignment_id', 'procedure_id');
        });

        Schema::table('payout_batches', function (Blueprint $table) {
            $table->renameColumn('payee_id', 'instrumentist_id');
        });
    }
};
