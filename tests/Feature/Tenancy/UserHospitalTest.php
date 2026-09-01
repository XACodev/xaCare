<?php

use App\Models\Hospital;
use App\Models\User;

test('user belongs to a hospital', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id]);

    expect($user->hospital->is($hospital))->toBeTrue();
});
