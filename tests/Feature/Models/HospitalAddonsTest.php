<?php

use App\Models\Hospital;

it('reports a feature as available when it is in addons even if not in the plan', function () {
    $hospital = Hospital::factory()->create([
        'features' => ['qxlog', 'patients'],
        'addons' => ['admissions_custom_form'],
    ]);

    expect($hospital->hasFeature('admissions_custom_form'))->toBeTrue();
    expect($hospital->hasFeature('admissions_id_documents'))->toBeFalse();
    expect($hospital->hasFeature('qxlog'))->toBeTrue();
});

it('defaults addons to an empty array', function () {
    $hospital = Hospital::factory()->create(['addons' => null]);

    expect($hospital->hasFeature('admissions_custom_form'))->toBeFalse();
});
