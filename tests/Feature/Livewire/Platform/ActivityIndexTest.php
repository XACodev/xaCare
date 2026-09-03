<?php

use App\Models\Hospital;
use App\Models\User;

test('activity index lists paginated activity log entries', function () {
    $hospital = Hospital::factory()->create();
    $causer = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    activity()->causedBy($causer)->performedOn($hospital)->log('creó el hospital');

    $this->actingAs($admin)
        ->get(route('platform.activity.index'))
        ->assertOk()
        ->assertSee('creó el hospital');
});
