<?php

use App\Models\Hospital;
use App\Models\User;

test('a platform admin can reach the platform dashboard', function () {
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $this->actingAs($admin)
        ->get(route('platform.dashboard'))
        ->assertOk();
});

test('a hospital admin is forbidden from every platform route', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false, 'role' => 'admin']);

    $this->actingAs($admin);

    $this->get(route('platform.dashboard'))->assertForbidden();
    $this->get(route('platform.hospitals.index'))->assertForbidden();
    $this->get(route('platform.roles.index'))->assertForbidden();
    $this->get(route('platform.permissions.index'))->assertForbidden();
    $this->get(route('platform.activity.index'))->assertForbidden();
    $this->get(route('platform.admins.index'))->assertForbidden();
});

test('a guest is redirected to login for every platform route', function () {
    $this->get(route('platform.dashboard'))->assertRedirect(route('login'));
});
