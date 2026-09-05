<?php

use App\Models\Hospital;
use App\Models\User;

test('a platform admin without confirmed two factor is redirected to the two-factor settings page', function () {
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $this->actingAs($admin)
        ->get(route('platform.dashboard'))
        ->assertRedirect(route('two-factor.show'));
});

test('a platform admin with confirmed two factor can reach the platform dashboard', function () {
    $admin = User::factory()->withTwoFactor()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $this->actingAs($admin)
        ->get(route('platform.dashboard'))
        ->assertOk();
});

test('a non platform admin still gets forbidden instead of the two-factor redirect', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false, 'role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('platform.dashboard'))
        ->assertForbidden();
});
