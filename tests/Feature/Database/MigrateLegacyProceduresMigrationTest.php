<?php
// tests/Feature/Database/MigrateLegacyProceduresMigrationTest.php

use App\Models\Hospital;
use App\Models\PricingSetting;
use App\Modules\QxLog\Models\RateModifier;
use App\Modules\QxLog\Models\RoleRate;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El entorno de test ya corre TODAS las migraciones (incluyendo el drop de columnas legacy)
 * antes de cada test via RefreshDatabase, así que cuando este test arranca las columnas
 * legacy de `surgical_cases` ya no existen. Para poder ejercitar el up() de esta migración en
 * el escenario "columnas presentes con datos reales", hay que volver a agregarlas
 * temporalmente -- mismo patrón que usa DropLegacyColumnsMigrationTest.php para el problema
 * inverso.
 */
function loadMigrateLegacyProceduresMigration(): object
{
    return include database_path('migrations/2026_09_02_110000_migrate_legacy_procedures_to_surgical_assignments.php');
}

function addLegacyColumnsToSurgicalCases(): void
{
    Schema::table('surgical_cases', function (Blueprint $table) {
        // Mismos tipos que la migración original de creación de `procedures`
        // (2026_01_12_165926_create_procedures_table.php).
        $table->foreignId('instrumentist_id')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('circulating_id')->nullable()->constrained('users')->nullOnDelete();

        $table->string('instrumentist_name')->nullable();
        $table->string('doctor_name')->nullable();
        $table->string('circulating_name')->nullable();

        $table->index(['instrumentist_id', 'status'], 'instrumentist_status_idx');
        $table->index(['doctor_id', 'status'], 'doctor_status_idx');
        $table->index(['circulating_id', 'status'], 'circulating_status_idx');
    });
}

function dropLegacyColumnsFromSurgicalCases(): void
{
    // Reutilizamos la migración de drop, que ya sabe reconstruir la tabla correctamente en
    // SQLite (dropColumn directo no funciona ahí por las foreign keys inline).
    $dropMigration = include database_path('migrations/2026_09_02_120000_drop_legacy_columns_from_surgical_cases.php');
    $dropMigration->up();
}

function insertLegacySurgicalCaseForMigrateTest(int $hospitalId, array $overrides = []): int
{
    return DB::table('surgical_cases')->insertGetId(array_merge([
        'procedure_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'patient_name' => 'Paciente Legacy',
        'procedure_type' => 'Apendicectomia',
        'instrumentist_id' => null,
        'doctor_id' => null,
        'circulating_id' => null,
        'instrumentist_name' => null,
        'doctor_name' => null,
        'circulating_name' => null,
        'calculated_amount' => 250,
        'pricing_snapshot' => json_encode(['is_courtesy' => false]),
        'status' => 'paid',
        'hospital_id' => $hospitalId,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

test('migra roles, tarifas y asignaciones desde datos legacy cuando las columnas existen', function () {
    addLegacyColumnsToSurgicalCases();

    $hospital = Hospital::factory()->create();
    $instrumentist = \App\Models\User::factory()->create(['hospital_id' => $hospital->id]);
    $doctor = \App\Models\User::factory()->create(['hospital_id' => $hospital->id]);

    PricingSetting::withoutGlobalScopes()->create([
        'hospital_id' => $hospital->id,
        'default_rate' => 100,
        'video_rate' => 20,
        'night_rate' => 30,
        'long_case_rate' => 40,
        'long_case_threshold_minutes' => 180,
        'night_start' => '20:00',
        'night_end' => '06:00',
    ]);

    $caseId = insertLegacySurgicalCaseForMigrateTest($hospital->id, [
        'instrumentist_id' => $instrumentist->id,
        'doctor_id' => $doctor->id,
        'circulating_id' => null,
        'circulating_name' => 'Circulante Free Text',
        'calculated_amount' => 250,
    ]);

    $migration = loadMigrateLegacyProceduresMigration();
    $migration->up();

    // Los 3 roles se crearon para el hospital.
    $roles = SurgicalRole::withoutGlobalScopes()->where('hospital_id', $hospital->id)->pluck('name', 'slug');
    expect($roles)->toHaveCount(3)
        ->and($roles->values()->sort()->values()->all())->toBe(['Circulante', 'Cirujano', 'Instrumentista']);

    $instrumentistRole = SurgicalRole::withoutGlobalScopes()
        ->where('hospital_id', $hospital->id)->where('slug', 'instrumentista')->first();

    // El RoleRate + los 3 RateModifiers se migraron desde PricingSetting.
    $rate = RoleRate::withoutGlobalScopes()
        ->where('surgical_role_id', $instrumentistRole->id)
        ->whereNull('user_id')->whereNull('procedure_type')->first();

    expect($rate)->not->toBeNull()
        ->and((float) $rate->base_rate)->toBe(100.0);

    $modifiers = RateModifier::withoutGlobalScopes()->where('role_rate_id', $rate->id)->get();
    expect($modifiers)->toHaveCount(3);

    // Los 3 SurgicalAssignment se crearon correctamente para el caso.
    $assignments = SurgicalAssignment::withoutGlobalScopes()->where('surgical_case_id', $caseId)->get()
        ->keyBy(fn ($a) => $a->surgicalRole->slug);

    expect($assignments)->toHaveCount(3);

    $instrumentistAssignment = $assignments['instrumentista'];
    expect($instrumentistAssignment->user_id)->toBe($instrumentist->id)
        ->and((float) $instrumentistAssignment->calculated_amount)->toBe(250.0)
        ->and($instrumentistAssignment->status)->toBe('paid')
        ->and($instrumentistAssignment->is_courtesy)->toBeFalse();

    $doctorAssignment = $assignments['cirujano'];
    expect($doctorAssignment->user_id)->toBe($doctor->id)
        ->and((float) $doctorAssignment->calculated_amount)->toBe(0.0)
        ->and($doctorAssignment->status)->toBe('paid');

    // Circulante sin user_id vinculado (solo texto libre): user_id queda null, sin rastro del
    // nombre libre -- comportamiento heredado del comando original, preservado a propósito.
    $circulatingAssignment = $assignments['circulante'];
    expect($circulatingAssignment->user_id)->toBeNull()
        ->and((float) $circulatingAssignment->calculated_amount)->toBe(0.0)
        ->and($circulatingAssignment->status)->toBe('paid');

    dropLegacyColumnsFromSurgicalCases();
});

test('es un no-op seguro cuando las columnas legacy ya no existen', function () {
    expect(Schema::hasColumn('surgical_cases', 'instrumentist_id'))->toBeFalse();

    $migration = loadMigrateLegacyProceduresMigration();

    expect(fn () => $migration->up())->not->toThrow(Throwable::class);

    // No se creó nada: no había columnas legacy que leer.
    expect(SurgicalRole::withoutGlobalScopes()->count())->toBe(0)
        ->and(SurgicalAssignment::withoutGlobalScopes()->count())->toBe(0);
});

test('es idempotente: correr up() dos veces no duplica roles, tarifas ni asignaciones', function () {
    addLegacyColumnsToSurgicalCases();

    $hospital = Hospital::factory()->create();
    $instrumentist = \App\Models\User::factory()->create(['hospital_id' => $hospital->id]);

    PricingSetting::withoutGlobalScopes()->create([
        'hospital_id' => $hospital->id,
        'default_rate' => 100,
        'video_rate' => 20,
        'night_rate' => 30,
        'long_case_rate' => 40,
        'long_case_threshold_minutes' => 180,
        'night_start' => '20:00',
        'night_end' => '06:00',
    ]);

    insertLegacySurgicalCaseForMigrateTest($hospital->id, ['instrumentist_id' => $instrumentist->id]);

    $migration = loadMigrateLegacyProceduresMigration();
    $migration->up();
    $migration->up();

    expect(SurgicalRole::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count())->toBe(3)
        ->and(RoleRate::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count())->toBe(1)
        ->and(RateModifier::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count())->toBe(3)
        ->and(SurgicalAssignment::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count())->toBe(3);

    dropLegacyColumnsFromSurgicalCases();
});
