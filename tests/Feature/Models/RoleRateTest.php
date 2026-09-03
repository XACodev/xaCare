<?php

use App\Models\Hospital;
use App\Modules\QxLog\Models\RoleRate;
use App\Modules\QxLog\Models\SurgicalRole;
use App\Models\User;

test('role rate belongs to its role and optionally to a user', function () {
    $hospital = Hospital::factory()->create();
    $this->actingAs(User::factory()->create(['hospital_id' => $hospital->id]));

    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create();
    $doctor = User::factory()->create(['hospital_id' => $hospital->id]);

    $default = RoleRate::factory()->for($role, 'surgicalRole')->create(['base_rate' => 200]);
    $override = RoleRate::factory()->for($role, 'surgicalRole')->create(['user_id' => $doctor->id, 'procedure_type' => 'Cesárea', 'base_rate' => 2000]);

    expect($default->user)->toBeNull()
        ->and($override->user->id)->toBe($doctor->id)
        ->and($override->surgicalRole->id)->toBe($role->id);
});
