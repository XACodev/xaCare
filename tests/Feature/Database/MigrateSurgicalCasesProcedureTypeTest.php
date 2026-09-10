<?php

use App\Models\Hospital;
use App\Modules\QxLog\Models\ProcedureType;
use Illuminate\Support\Facades\DB;

/**
 * El entorno de test ya corre TODAS las migraciones (incluyendo el drop de la columna string
 * legacy) antes de cada test via RefreshDatabase, así que cuando este test arranca la columna
 * `procedure_type` ya no existe. Para poder ejercitar el up() de la migración de backfill en el
 * escenario "columna string presente con datos reales", hay que restaurarla primero con la
 * migración de drop -- mismo patrón que usa MigrateRoleRatesProcedureTypeTest.php para el mismo
 * problema con `role_rates`.
 */
function loadAddProcedureTypeIdToSurgicalCasesMigration(): object
{
    return include database_path('migrations/2026_09_10_100300_add_procedure_type_id_to_surgical_cases_table.php');
}

function loadDropProcedureTypeFromSurgicalCasesMigration(): object
{
    return include database_path('migrations/2026_09_10_100400_drop_procedure_type_from_surgical_cases_table.php');
}

test('el backfill de surgical_cases reutiliza el mismo ProcedureType que el de role_rates para el mismo hospital', function () {
    $dropMigration = loadDropProcedureTypeFromSurgicalCasesMigration();
    $dropMigration->down(); // restaura la columna string `procedure_type`, ya dropeada por RefreshDatabase

    $migration = loadAddProcedureTypeIdToSurgicalCasesMigration();
    $migration->down(); // vuelve a dejar solo la columna string, para simular datos pre-migración

    $hospital = Hospital::factory()->create();
    $existing = ProcedureType::factory()->for($hospital, 'hospital')->create(['name' => 'Cesárea']);

    $id = DB::table('surgical_cases')->insertGetId([
        'hospital_id' => $hospital->id, 'procedure_date' => now()->toDateString(),
        'start_time' => '08:00', 'end_time' => '09:00', 'duration_minutes' => 60,
        'patient_name' => 'Paciente', 'procedure_type' => ' CESÁREA ',
        'is_videosurgery' => false, 'status' => 'pending', 'calculated_amount' => 0,
        'is_draft' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $idNull = DB::table('surgical_cases')->insertGetId([
        'hospital_id' => $hospital->id, 'procedure_date' => now()->toDateString(),
        'start_time' => '08:00', 'end_time' => '09:00', 'duration_minutes' => 60,
        'patient_name' => 'Paciente 2', 'procedure_type' => null,
        'is_videosurgery' => false, 'status' => 'pending', 'calculated_amount' => 0,
        'is_draft' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $migration->up();

    expect(DB::table('surgical_cases')->where('id', $id)->value('procedure_type_id'))->toBe($existing->id)
        ->and(DB::table('surgical_cases')->where('id', $idNull)->value('procedure_type_id'))->toBeNull()
        ->and(ProcedureType::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count())->toBe(1);

    // Deja la BD como la encontró (con procedure_type ya dropeada), igual que
    // MigrateRoleRatesProcedureTypeTest.php hace con sus columnas legacy.
    $dropMigration->up();
});
