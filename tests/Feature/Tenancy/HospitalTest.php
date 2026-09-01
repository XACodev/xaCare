<?php

use App\Models\Hospital;

test('hospital can be created and reports features', function () {
    $hospital = Hospital::factory()->create([
        'name' => 'HNSC',
        'features' => ['insurance_automation'],
    ]);

    expect($hospital->name)->toBe('HNSC');
    expect($hospital->hasFeature('insurance_automation'))->toBeTrue();
    expect($hospital->hasFeature('ehr'))->toBeFalse();
});
