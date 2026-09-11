<?php

use App\Models\Hospital;
use App\Modules\QxLog\Models\SurgicalRole;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Hospital::query()->get() as $hospital) {
            SurgicalRole::seedDefaultsFor($hospital);
        }
    }

    public function down(): void
    {
        // No-op: mismo criterio que 2026_09_09_090300_backfill_operating_rooms_and_surgery_statuses —
        // revertir borraria roles que ya pueden estar en uso por surgical_assignments reales.
    }
};
