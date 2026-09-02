<?php

use App\Models\Hospital;
use App\Models\RateModifier;
use App\Models\RoleRate;
use App\Models\SurgicalRole;
use App\Models\User;

test('rate modifier stores trigger config as array and detects manual toggles', function () {
    $hospital = Hospital::factory()->create();
    $this->actingAs(User::factory()->create(['hospital_id' => $hospital->id]));

    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create();
    $rate = RoleRate::factory()->for($role, 'surgicalRole')->create();

    $automatic = RateModifier::factory()->for($rate, 'roleRate')->create();
    $manual = RateModifier::factory()->for($rate, 'roleRate')->manualToggle('Video')->create();

    expect($automatic->trigger_config)->toBe(['start' => '22:00', 'end' => '06:00'])
        ->and($automatic->isManual())->toBeFalse()
        ->and($manual->isManual())->toBeTrue()
        ->and($rate->modifiers)->toHaveCount(2);
});
