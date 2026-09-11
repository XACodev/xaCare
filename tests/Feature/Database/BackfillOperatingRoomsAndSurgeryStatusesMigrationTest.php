<?php

use App\Models\Hospital;
use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgeryStatus;

/**
 * RefreshDatabase ya corre esta migración (sin hospitales todavía) antes de cada test. Para
 * ejercitar su up() contra hospitales reales, se vuelve a invocar explícitamente después de
 * sembrarlos — mismo patrón que MigrateLegacyRolesToSpatieTeamsMigrationTest.
 */
function loadBackfillOperatingRoomsAndSurgeryStatusesMigration(): object
{
    return include database_path('migrations/2026_09_09_090300_backfill_operating_rooms_and_surgery_statuses.php');
}

test('crea un quirófano principal y el catálogo de estados por defecto para cada hospital', function () {
    $hospital = Hospital::factory()->create();

    loadBackfillOperatingRoomsAndSurgeryStatusesMigration()->up();

    $room = OperatingRoom::withoutGlobalScopes()->where('hospital_id', $hospital->id)->first();
    expect($room)->not->toBeNull()
        ->and($room->name)->toBe('Principal')
        ->and($room->is_default)->toBeTrue();

    $statuses = SurgeryStatus::withoutGlobalScopes()->where('hospital_id', $hospital->id)->get();
    expect($statuses->pluck('slug')->sort()->values()->all())
        ->toBe(['cancelada', 'completada', 'en-curso', 'programada']);

    $default = $statuses->firstWhere('is_default', true);
    $completed = $statuses->firstWhere('is_completed', true);
    $cancelled = $statuses->firstWhere('is_cancelled', true);

    expect($default->slug)->toBe('programada')
        ->and($completed->slug)->toBe('completada')
        ->and($cancelled->slug)->toBe('cancelada');
});

test('no duplica quirófano ni estados si el hospital ya tiene alguno', function () {
    $hospital = Hospital::factory()->create();
    OperatingRoom::withoutGlobalScopes()->where('hospital_id', $hospital->id)->delete();
    SurgeryStatus::withoutGlobalScopes()->where('hospital_id', $hospital->id)->delete();
    OperatingRoom::factory()->for($hospital, 'hospital')->create(['name' => 'Quirófano A']);
    SurgeryStatus::factory()->for($hospital, 'hospital')->create(['name' => 'Personalizado']);

    loadBackfillOperatingRoomsAndSurgeryStatusesMigration()->up();

    expect(OperatingRoom::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count())->toBe(1)
        ->and(SurgeryStatus::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count())->toBe(1);
});

test('correr la migración dos veces es idempotente', function () {
    $hospital = Hospital::factory()->create();

    $migration = loadBackfillOperatingRoomsAndSurgeryStatusesMigration();
    $migration->up();
    $migration->up();

    expect(OperatingRoom::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count())->toBe(1)
        ->and(SurgeryStatus::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count())->toBe(4);
});
