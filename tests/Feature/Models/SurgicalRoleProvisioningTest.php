<?php

use App\Models\Hospital;
use App\Modules\QxLog\Models\SurgicalRole;

test('un hospital nuevo recibe los 3 roles quirurgicos base', function () {
    $hospital = Hospital::factory()->create();

    $names = SurgicalRole::withoutGlobalScopes()->where('hospital_id', $hospital->id)->orderBy('sort_order')->pluck('name');

    expect($names->all())->toBe(['Cirujano', 'Instrumentista', 'Circulante']);
});

test('seedDefaultsFor no duplica si el hospital ya tiene roles', function () {
    $hospital = Hospital::factory()->create();
    expect(SurgicalRole::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count())->toBe(3);

    SurgicalRole::seedDefaultsFor($hospital);

    expect(SurgicalRole::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count())->toBe(3);
});
