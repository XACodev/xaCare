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
            $this->seedDefaultOperatingRoom($hospital);
            $this->seedDefaultSurgeryStatuses($hospital);
        }
    }

    public function down(): void
    {
        // No-op: revertir borraría catálogos que el hospital pudo haber personalizado desde
        // que se sembraron, con riesgo de perder datos reales (surgical_cases ya podrían
        // referenciarlos). Ver la misma política en drop_legacy_columns_from_surgical_cases.
    }

    private function seedDefaultOperatingRoom(Hospital $hospital): void
    {
        $exists = OperatingRoom::withoutGlobalScopes()->where('hospital_id', $hospital->id)->exists();

        if ($exists) {
            return;
        }

        OperatingRoom::withoutGlobalScopes()->create([
            'hospital_id' => $hospital->id,
            'name' => 'Principal',
            'is_default' => true,
            'active' => true,
            'sort_order' => 0,
        ]);
    }

    private function seedDefaultSurgeryStatuses(Hospital $hospital): void
    {
        $exists = SurgeryStatus::withoutGlobalScopes()->where('hospital_id', $hospital->id)->exists();

        if ($exists) {
            return;
        }

        $statuses = [
            ['name' => 'Programada', 'slug' => 'programada', 'sort_order' => 0, 'is_default' => true],
            ['name' => 'Confirmada', 'slug' => 'confirmada', 'sort_order' => 1],
            ['name' => 'En curso', 'slug' => 'en-curso', 'sort_order' => 2],
            ['name' => 'Completada', 'slug' => 'completada', 'sort_order' => 3, 'is_completed' => true],
            ['name' => 'Cancelada', 'slug' => 'cancelada', 'sort_order' => 4, 'is_cancelled' => true],
        ];

        foreach ($statuses as $status) {
            SurgeryStatus::withoutGlobalScopes()->create([
                'hospital_id' => $hospital->id,
                'name' => $status['name'],
                'slug' => $status['slug'],
                'sort_order' => $status['sort_order'],
                'is_default' => $status['is_default'] ?? false,
                'is_completed' => $status['is_completed'] ?? false,
                'is_cancelled' => $status['is_cancelled'] ?? false,
                'active' => true,
            ]);
        }
    }
};
