<?php
// tests/Feature/Database/DropLegacyColumnsMigrationTest.php

use App\Models\Hospital;
use App\Models\SurgicalAssignment;
use App\Models\SurgicalRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El entorno de test ya corre TODAS las migraciones (incluyendo el drop) antes de cada test
 * via RefreshDatabase, así que las columnas legacy ya no existen cuando el test arranca. Para
 * poder ejercitar el up() de la migración de drop en ambos escenarios (bloqueo y éxito),
 * cargamos la migración y usamos su propio down() para restaurar el esquema pre-drop.
 */
function loadDropLegacyColumnsMigration(): object
{
    return include database_path('migrations/2026_09_02_120000_drop_legacy_columns_from_surgical_cases.php');
}

function insertLegacySurgicalCase(int $hospitalId, ?int $instrumentistId): int
{
    return DB::table('surgical_cases')->insertGetId([
        'procedure_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '09:00',
        'patient_name' => 'Paciente Legacy',
        'procedure_type' => 'Apendicectomia',
        'instrumentist_id' => $instrumentistId,
        'calculated_amount' => 100,
        'status' => 'pending',
        'hospital_id' => $hospitalId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('bloquea el drop si un caso tiene datos legacy sin ningun surgical_assignment', function () {
    $migration = loadDropLegacyColumnsMigration();
    $migration->down(); // restaurar esquema pre-drop para poder simular el escenario

    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id]);

    // Escenario "datos reales sin migrar": instrumentist_id poblado, CERO surgical_assignments
    // para ese caso.
    $caseId = insertLegacySurgicalCase($hospital->id, $user->id);

    expect(SurgicalAssignment::withoutGlobalScopes()->where('surgical_case_id', $caseId)->exists())
        ->toBeFalse();

    expect(fn () => $migration->up())->toThrow(
        RuntimeException::class,
        'No se puede eliminar columnas legacy: 1 casos quirúrgicos tienen datos de instrumentista/doctor/circulante (por ID o por nombre libre) que nunca se migraron a surgical_assignments.'
    );

    // La guardia debe proteger a nivel de schema: la migración no debe haber dropeado nada.
    expect(Schema::hasColumn('surgical_cases', 'instrumentist_id'))->toBeTrue()
        ->and(Schema::hasColumn('surgical_cases', 'instrumentist_name'))->toBeTrue()
        ->and(Schema::hasColumn('surgical_cases', 'doctor_id'))->toBeTrue()
        ->and(Schema::hasColumn('surgical_cases', 'doctor_name'))->toBeTrue()
        ->and(Schema::hasColumn('surgical_cases', 'circulating_id'))->toBeTrue()
        ->and(Schema::hasColumn('surgical_cases', 'circulating_name'))->toBeTrue();

    // Y los datos legacy siguen intactos: nada se perdió.
    expect(DB::table('surgical_cases')->where('id', $caseId)->value('instrumentist_id'))
        ->toBe($user->id);
});

test('bloquea el drop si un caso tiene solo doctor_name/circulating_name de texto libre, sin ningun ID ni surgical_assignment', function () {
    // Escenario de emergencia: doctor y circulante cargados como texto libre porque no había un
    // User vinculado en el momento del registro (ver commit 7be47c5). Las 3 columnas _id quedan
    // en null -- si la guardia solo mirara los IDs, este caso pasaría desapercibido y sus
    // nombres se perderían para siempre al dropear las columnas.
    $migration = loadDropLegacyColumnsMigration();
    $migration->down();

    $hospital = Hospital::factory()->create();

    $caseId = DB::table('surgical_cases')->insertGetId([
        'procedure_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '09:00',
        'patient_name' => 'Paciente Emergencia',
        'procedure_type' => 'Apendicectomia',
        'instrumentist_id' => null,
        'doctor_id' => null,
        'circulating_id' => null,
        'doctor_name' => 'Dr. Free Text',
        'circulating_name' => 'Circulante Free Text',
        'calculated_amount' => 100,
        'status' => 'pending',
        'hospital_id' => $hospital->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(SurgicalAssignment::withoutGlobalScopes()->where('surgical_case_id', $caseId)->exists())
        ->toBeFalse();

    expect(fn () => $migration->up())->toThrow(
        RuntimeException::class,
        'No se puede eliminar columnas legacy: 1 casos quirúrgicos tienen datos de instrumentista/doctor/circulante (por ID o por nombre libre) que nunca se migraron a surgical_assignments.'
    );

    // La guardia debe proteger a nivel de schema: la migración no debe haber dropeado nada.
    expect(Schema::hasColumn('surgical_cases', 'instrumentist_id'))->toBeTrue()
        ->and(Schema::hasColumn('surgical_cases', 'instrumentist_name'))->toBeTrue()
        ->and(Schema::hasColumn('surgical_cases', 'doctor_id'))->toBeTrue()
        ->and(Schema::hasColumn('surgical_cases', 'doctor_name'))->toBeTrue()
        ->and(Schema::hasColumn('surgical_cases', 'circulating_id'))->toBeTrue()
        ->and(Schema::hasColumn('surgical_cases', 'circulating_name'))->toBeTrue();

    // Y los datos legacy siguen intactos: nada se perdió.
    expect(DB::table('surgical_cases')->where('id', $caseId)->value('doctor_name'))
        ->toBe('Dr. Free Text')
        ->and(DB::table('surgical_cases')->where('id', $caseId)->value('circulating_name'))
        ->toBe('Circulante Free Text');
});

test('permite el drop cuando el caso legacy ya tiene al menos un surgical_assignment', function () {
    $migration = loadDropLegacyColumnsMigration();
    $migration->down();

    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $role = SurgicalRole::factory()->create(['hospital_id' => $hospital->id]);

    $caseId = insertLegacySurgicalCase($hospital->id, $user->id);

    SurgicalAssignment::withoutGlobalScopes()->create([
        'hospital_id' => $hospital->id,
        'surgical_case_id' => $caseId,
        'surgical_role_id' => $role->id,
        'user_id' => $user->id,
        'calculated_amount' => 100,
        'status' => 'paid',
    ]);

    $migration->up();

    expect(Schema::hasColumn('surgical_cases', 'instrumentist_id'))->toBeFalse()
        ->and(Schema::hasColumn('surgical_cases', 'instrumentist_name'))->toBeFalse()
        ->and(Schema::hasColumn('surgical_cases', 'doctor_id'))->toBeFalse()
        ->and(Schema::hasColumn('surgical_cases', 'doctor_name'))->toBeFalse()
        ->and(Schema::hasColumn('surgical_cases', 'circulating_id'))->toBeFalse()
        ->and(Schema::hasColumn('surgical_cases', 'circulating_name'))->toBeFalse();
});

test('permite el drop cuando no hay ningun caso con datos legacy', function () {
    $migration = loadDropLegacyColumnsMigration();
    $migration->down();

    $migration->up();

    expect(Schema::hasColumn('surgical_cases', 'instrumentist_id'))->toBeFalse();
});
