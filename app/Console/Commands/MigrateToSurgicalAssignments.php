<?php
// app/Console/Commands/MigrateToSurgicalAssignments.php
namespace App\Console\Commands;

use App\Models\Hospital;
use App\Models\PricingSetting;
use App\Models\RateModifier;
use App\Models\RoleRate;
use App\Models\SurgicalAssignment;
use App\Models\SurgicalCase;
use App\Models\SurgicalRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateToSurgicalAssignments extends Command
{
    protected $signature = 'xacare:migrate-to-surgical-assignments {--hospital=}';

    protected $description = 'Migra procedures legacy (instrumentista/doctor/circulante) a surgical_assignments, sembrando el catálogo de roles y tarifas por hospital.';

    public function handle(): int
    {
        $hospitals = Hospital::query()
            ->when($this->option('hospital'), fn ($q, $slug) => $q->where('slug', $slug))
            ->get();

        foreach ($hospitals as $hospital) {
            DB::transaction(function () use ($hospital) {
                $roles = $this->seedRoles($hospital);
                $this->migratePricingSetting($hospital, $roles['Instrumentista']);
                $this->migrateAssignments($hospital, $roles);
            });

            $this->info("Hospital {$hospital->name}: migración completa.");
        }

        return self::SUCCESS;
    }

    /** @return array<string, SurgicalRole> */
    private function seedRoles(Hospital $hospital): array
    {
        $names = ['Instrumentista', 'Cirujano', 'Circulante'];
        $roles = [];

        foreach ($names as $name) {
            $roles[$name] = SurgicalRole::withoutGlobalScopes()->firstOrCreate(
                ['hospital_id' => $hospital->id, 'slug' => \Illuminate\Support\Str::slug($name)],
                ['name' => $name, 'is_payable' => true, 'active' => true, 'sort_order' => 0],
            );
        }

        return $roles;
    }

    private function migratePricingSetting(Hospital $hospital, SurgicalRole $instrumentistRole): void
    {
        $existing = RoleRate::withoutGlobalScopes()
            ->where('surgical_role_id', $instrumentistRole->id)
            ->whereNull('user_id')->whereNull('procedure_type')
            ->first();

        if ($existing) {
            return; // ya migrado, idempotente
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
            'hospital_id' => $hospital->id, 'role_rate_id' => $rate->id,
            'name' => 'Video', 'trigger_type' => RateModifier::TRIGGER_MANUAL_TOGGLE, 'trigger_config' => [],
            'rate_type' => RateModifier::RATE_FIXED_AMOUNT, 'amount' => $settings->video_rate, 'active' => true, 'sort_order' => 1,
        ]);

        RateModifier::withoutGlobalScopes()->create([
            'hospital_id' => $hospital->id, 'role_rate_id' => $rate->id,
            'name' => 'Nocturno', 'trigger_type' => RateModifier::TRIGGER_TIME_WINDOW,
            'trigger_config' => ['start' => (string) $settings->night_start, 'end' => (string) $settings->night_end],
            'rate_type' => RateModifier::RATE_FIXED_AMOUNT, 'amount' => $settings->night_rate, 'active' => true, 'sort_order' => 2,
        ]);

        RateModifier::withoutGlobalScopes()->create([
            'hospital_id' => $hospital->id, 'role_rate_id' => $rate->id,
            'name' => 'Caso largo', 'trigger_type' => RateModifier::TRIGGER_DURATION_GTE,
            'trigger_config' => ['minutes' => (int) $settings->long_case_threshold_minutes],
            'rate_type' => RateModifier::RATE_FIXED_AMOUNT, 'amount' => $settings->long_case_rate, 'active' => true, 'sort_order' => 3,
        ]);
    }

    /** @param array<string, SurgicalRole> $roles */
    private function migrateAssignments(Hospital $hospital, array $roles): void
    {
        $cases = SurgicalCase::withoutGlobalScopes()->where('hospital_id', $hospital->id)->get();

        foreach ($cases as $case) {
            $this->migrateOneAssignment($case, $roles['Instrumentista'], $case->instrumentist_id, historic: true);
            $this->migrateOneAssignment($case, $roles['Cirujano'], $case->doctor_id, historic: false);
            $this->migrateOneAssignment($case, $roles['Circulante'], $case->circulating_id, historic: false);
        }
    }

    private function migrateOneAssignment(SurgicalCase $case, SurgicalRole $role, ?int $userId, bool $historic): void
    {
        $already = SurgicalAssignment::withoutGlobalScopes()
            ->where('surgical_case_id', $case->id)->where('surgical_role_id', $role->id)->exists();

        if ($already) {
            return; // idempotente
        }

        if (! $userId && ! $historic) {
            // Sin usuario del sistema (doctor/circulante libre por texto): igual se registra la asignación
            // sin honorario histórico, para no perder el registro de quién participó.
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
}
