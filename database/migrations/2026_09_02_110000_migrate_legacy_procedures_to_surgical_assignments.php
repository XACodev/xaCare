<?php
// database/migrations/2026_09_02_110000_migrate_legacy_procedures_to_surgical_assignments.php
use App\Models\Hospital;
use App\Models\PricingSetting;
use App\Modules\QxLog\Models\RateModifier;
use App\Modules\QxLog\Models\RoleRate;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\SurgicalRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Migra los datos legacy de instrumentista/doctor/circulante de `surgical_cases`
     * (columnas `instrumentist_id`/`doctor_id`/`circulating_id` y sus contrapartes `_name`)
     * hacia `surgical_assignments`, junto con el `RoleRate`/`RateModifier` de instrumentista
     * derivado de `PricingSetting`. Reemplaza al comando artisan
     * `xacare:migrate-to-surgical-assignments` (retirado en la rama que dropea las columnas
     * legacy) porque el pipeline de deploy de Laravel Cloud solo corre
     * `php artisan migrate --force` -- no ejecuta comandos artisan custom -- así que esta
     * lógica tiene que vivir en una migración real para correr sola en cualquier entorno
     * (dev fresco, staging, producción) antes de que
     * `2026_09_02_120000_drop_legacy_columns_from_surgical_cases.php` dropee esas columnas.
     */
    public function up(): void
    {
        // GUARDIA DE SEGURIDAD: esta migración solo tiene sentido si las columnas legacy
        // todavía existen en `surgical_cases`. Hay dos escenarios legítimos en los que ya NO
        // existen cuando esta migración corre:
        //   1. Un entorno (como `develop` al momento de escribir esto) donde la migración de
        //      drop (2026_09_02_120000) ya corrió ANTES de que esta migración (con timestamp
        //      anterior, 2026_09_02_110000) se agregara al repo -- Laravel la ve "pendiente"
        //      la primera vez que corre `migrate` después de este commit y la ejecuta, pero ya
        //      no queda nada que migrar porque el drop ya se hizo (y su propia guardia ya
        //      confirmó en su momento que no había datos legacy sin migrar).
        //   2. Cualquier entorno que se cree DESPUÉS de que esta migración exista en el repo
        //      seguirá el orden correcto (esta migración primero, drop después), por lo que
        //      este caso no debería darse en la práctica, pero igual lo cubrimos.
        // En ambos casos, no hay nada que leer ni migrar: debe ser un no-op silencioso, nunca
        // un error.
        if (! Schema::hasColumn('surgical_cases', 'instrumentist_id')
            || ! Schema::hasColumn('surgical_cases', 'doctor_id')
            || ! Schema::hasColumn('surgical_cases', 'circulating_id')) {
            return;
        }

        $hospitals = Hospital::query()->get();

        foreach ($hospitals as $hospital) {
            DB::transaction(function () use ($hospital) {
                $roles = $this->seedRoles($hospital);
                $this->migratePricingSetting($hospital, $roles['Instrumentista']);
                $this->migrateAssignments($hospital, $roles);
            });
        }
    }

    /**
     * No se puede revertir de forma segura: esta migración solo AGREGA filas
     * (`surgical_roles`/`role_rates`/`rate_modifiers`/`surgical_assignments`) derivadas de
     * datos legacy que siguen viviendo en `surgical_cases` mientras esas columnas existan --
     * borrarlas en down() perdería asignaciones que ya podrían tener otras relaciones
     * dependientes (payout_item_id, activity log, etc.) creadas después de la migración.
     * Mismo criterio que `down()` en 2026_09_02_120000_drop_legacy_columns_from_surgical_cases.php:
     * un rollback con datos reales requeriría restaurar desde un backup tomado antes de correr
     * esta migración, no algo que se pueda automatizar de forma segura acá.
     */
    public function down(): void
    {
        // Ver comentario de la clase: no-op intencional.
    }

    private function seedRoles(Hospital $hospital): array
    {
        $names = ['Instrumentista', 'Cirujano', 'Circulante'];
        $roles = [];

        foreach ($names as $name) {
            $roles[$name] = SurgicalRole::withoutGlobalScopes()->firstOrCreate(
                ['hospital_id' => $hospital->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'is_payable' => true, 'active' => true, 'sort_order' => 0],
            );
        }

        return $roles;
    }

    private function migratePricingSetting(Hospital $hospital, SurgicalRole $instrumentistRole): void
    {
        $existing = RoleRate::withoutGlobalScopes()
            ->where('surgical_role_id', $instrumentistRole->id)
            ->whereNull('user_id')
            ->whereNull('procedure_type')
            ->first();

        if ($existing) {
            return;
        }

        $settings = PricingSetting::withoutGlobalScopes()->where('hospital_id', $hospital->id)->first();

        if (! $settings) {
            return;
        }

        $rate = RoleRate::withoutGlobalScopes()->create([
            'hospital_id' => $hospital->id,
            'surgical_role_id' => $instrumentistRole->id,
            'user_id' => null,
            'procedure_type' => null,
            'base_rate' => $settings->default_rate,
            'active' => true,
        ]);

        RateModifier::withoutGlobalScopes()->create([
            'hospital_id' => $hospital->id,
            'role_rate_id' => $rate->id,
            'name' => 'Video',
            'trigger_type' => RateModifier::TRIGGER_MANUAL_TOGGLE,
            'trigger_config' => [],
            'rate_type' => RateModifier::RATE_FIXED_AMOUNT,
            'amount' => $settings->video_rate,
            'active' => true,
            'sort_order' => 1,
        ]);

        RateModifier::withoutGlobalScopes()->create([
            'hospital_id' => $hospital->id,
            'role_rate_id' => $rate->id,
            'name' => 'Nocturno',
            'trigger_type' => RateModifier::TRIGGER_TIME_WINDOW,
            'trigger_config' => ['start' => (string) $settings->night_start, 'end' => (string) $settings->night_end],
            'rate_type' => RateModifier::RATE_FIXED_AMOUNT,
            'amount' => $settings->night_rate,
            'active' => true,
            'sort_order' => 2,
        ]);

        RateModifier::withoutGlobalScopes()->create([
            'hospital_id' => $hospital->id,
            'role_rate_id' => $rate->id,
            'name' => 'Caso largo',
            'trigger_type' => RateModifier::TRIGGER_DURATION_GTE,
            'trigger_config' => ['minutes' => (int) $settings->long_case_threshold_minutes],
            'rate_type' => RateModifier::RATE_FIXED_AMOUNT,
            'amount' => $settings->long_case_rate,
            'active' => true,
            'sort_order' => 3,
        ]);
    }

    private function migrateAssignments(Hospital $hospital, array $roles): void
    {
        $cases = SurgicalCase::withoutGlobalScopes()->where('hospital_id', $hospital->id)->get();

        foreach ($cases as $case) {
            $this->migrateOneAssignment($case, $roles['Instrumentista'], $case->instrumentist_id, historic: true);
            $this->migrateOneAssignment($case, $roles['Cirujano'], $case->doctor_id, historic: false);
            $this->migrateOneAssignment($case, $roles['Circulante'], $case->circulating_id, historic: false);
        }
    }

    /**
     * NOTA: si $userId es null y $historic es false (caso legacy sin usuario de sistema
     * vinculado, solo texto libre en doctor_name/circulating_name), esta lógica NO copia ese
     * nombre a ningún lado -- el SurgicalAssignment queda con user_id=null sin rastro del
     * nombre. Este es el mismo comportamiento del comando original
     * (xacare:migrate-to-surgical-assignments, recuperado del historial de git) y se preserva
     * intencionalmente: `surgical_assignments` no tiene columna para texto libre, agregar una
     * sería un cambio de schema aparte y fuera de alcance de esta migración.
     */
    private function migrateOneAssignment(SurgicalCase $case, SurgicalRole $role, ?int $userId, bool $historic): void
    {
        $already = SurgicalAssignment::withoutGlobalScopes()
            ->where('surgical_case_id', $case->id)
            ->where('surgical_role_id', $role->id)
            ->exists();

        if ($already) {
            return;
        }

        SurgicalAssignment::withoutGlobalScopes()->create([
            'hospital_id' => $case->hospital_id,
            'surgical_case_id' => $case->id,
            'surgical_role_id' => $role->id,
            'user_id' => $userId,
            'calculated_amount' => $historic ? (float) $case->calculated_amount : 0.0,
            'pricing_snapshot' => $historic ? $case->pricing_snapshot : null,
            'is_courtesy' => $historic ? (bool) data_get($case->pricing_snapshot, 'is_courtesy', false) : false,
            'status' => $historic ? $case->status : 'paid',
        ]);
    }
};
