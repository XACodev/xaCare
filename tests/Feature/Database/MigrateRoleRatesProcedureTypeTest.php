<?php
// tests/Feature/Database/MigrateRoleRatesProcedureTypeTest.php

use App\Models\Hospital;
use App\Modules\QxLog\Models\ProcedureType;
use App\Modules\QxLog\Models\SurgicalRole;
use Illuminate\Support\Facades\DB;

/**
 * El entorno de test ya corre TODAS las migraciones (incluyendo el drop de la columna string
 * legacy) antes de cada test via RefreshDatabase, así que cuando este test arranca la columna
 * `procedure_type` ya no existe. Para poder ejercitar el up() de la migración de backfill en el
 * escenario "columna string presente con datos reales", hay que restaurarla primero con la
 * migración de drop -- mismo patrón que usa MigrateLegacyProceduresMigrationTest.php para el
 * mismo problema con `surgical_cases`.
 */
function loadAddProcedureTypeIdToRoleRatesMigration(): object
{
    return include database_path('migrations/2026_09_10_100100_add_procedure_type_id_to_role_rates_table.php');
}

function loadDropProcedureTypeFromRoleRatesMigration(): object
{
    return include database_path('migrations/2026_09_10_100200_drop_procedure_type_from_role_rates_table.php');
}

test('el backfill crea un ProcedureType por valor distinto y por hospital, normalizando espacios y mayúsculas', function () {
    $dropMigration = loadDropProcedureTypeFromRoleRatesMigration();
    $dropMigration->down(); // restaura la columna string `procedure_type`, ya dropeada por RefreshDatabase

    $migration = loadAddProcedureTypeIdToRoleRatesMigration();
    $migration->down(); // vuelve a dejar solo la columna string, para simular datos pre-migración

    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    $roleA = SurgicalRole::factory()->for($hospitalA, 'hospital')->create();
    $roleB = SurgicalRole::factory()->for($hospitalB, 'hospital')->create();

    $idExact = DB::table('role_rates')->insertGetId([
        'hospital_id' => $hospitalA->id, 'surgical_role_id' => $roleA->id,
        'procedure_type' => 'Cesárea', 'base_rate' => 1000, 'active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $idVariant = DB::table('role_rates')->insertGetId([
        'hospital_id' => $hospitalA->id, 'surgical_role_id' => $roleA->id,
        'procedure_type' => '  cesárea  ', 'base_rate' => 1200, 'active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $idOtherHospital = DB::table('role_rates')->insertGetId([
        'hospital_id' => $hospitalB->id, 'surgical_role_id' => $roleB->id,
        'procedure_type' => 'Cesárea', 'base_rate' => 900, 'active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $idNull = DB::table('role_rates')->insertGetId([
        'hospital_id' => $hospitalA->id, 'surgical_role_id' => $roleA->id,
        'procedure_type' => null, 'base_rate' => 500, 'active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $migration->up();

    $typeAId = DB::table('role_rates')->where('id', $idExact)->value('procedure_type_id');
    expect($typeAId)->not->toBeNull()
        ->and(DB::table('role_rates')->where('id', $idVariant)->value('procedure_type_id'))->toBe($typeAId)
        ->and(DB::table('role_rates')->where('id', $idNull)->value('procedure_type_id'))->toBeNull();

    $typeBId = DB::table('role_rates')->where('id', $idOtherHospital)->value('procedure_type_id');
    expect($typeBId)->not->toBe($typeAId);

    expect(ProcedureType::withoutGlobalScopes()->where('hospital_id', $hospitalA->id)->where('name', 'Cesárea')->count())
        ->toBe(1);

    // Deja la BD como la encontró (con procedure_type ya dropeada), igual que
    // MigrateLegacyProceduresMigrationTest.php hace con sus columnas legacy.
    $dropMigration->up();
});
