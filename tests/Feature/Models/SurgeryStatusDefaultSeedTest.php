<?php

use App\Models\Hospital;
use App\Modules\QxLog\Models\SurgeryStatus;

test('un hospital nuevo no recibe el estado Confirmada en el seed por defecto', function () {
    $hospital = Hospital::factory()->create();

    $names = SurgeryStatus::withoutGlobalScopes()
        ->where('hospital_id', $hospital->id)
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();

    expect($names)->toEqual(['Programada', 'En curso', 'Completada', 'Cancelada']);
});

test('Programada sigue siendo el estado por defecto', function () {
    $hospital = Hospital::factory()->create();

    $default = SurgeryStatus::withoutGlobalScopes()
        ->where('hospital_id', $hospital->id)
        ->where('is_default', true)
        ->first();

    expect($default->name)->toBe('Programada');
});
