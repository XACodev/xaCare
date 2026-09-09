<?php

use App\Models\Hospital;
use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgeryStatus;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Idempotente: si un hospital ya tiene algún OperatingRoom/SurgeryStatus (propio o creado
     * por una corrida anterior de esta misma migración), no crea nada más para él.
     */
    public function up(): void
    {
        foreach (Hospital::query()->get() as $hospital) {
            OperatingRoom::seedDefaultFor($hospital);
            SurgeryStatus::seedDefaultsFor($hospital);
        }
    }

    public function down(): void
    {
        // No-op: revertir borraría catálogos que el hospital pudo haber personalizado desde
        // que se sembraron, con riesgo de perder datos reales (surgical_cases ya podrían
        // referenciarlos). Ver la misma política en drop_legacy_columns_from_surgical_cases.
    }
};
