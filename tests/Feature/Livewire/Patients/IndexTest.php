<?php

use App\Models\User;

test('super admin can view the patients index', function () {
    $superAdmin = User::factory()->create(['is_platform_admin' => true, 'hospital_id' => null]);

    $this->actingAs($superAdmin)
        ->get(route('patients.index'))
        ->assertOk();
});

test('instrumentist cannot view the patients index', function () {
    $user = User::factory()->create(['role' => 'instrumentist']);

    $this->actingAs($user)
        ->get(route('patients.index'))
        ->assertUnauthorized();
});
