<?php

use App\Models\Hospital;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('non admin authenticated user cannot reach patients or admissions routes directly', function () {
    $user = User::factory()->create(['hospital_id' => Hospital::factory()->create()->id]);
    $this->actingAs($user);

    $this->get(route('patients.index'))->assertUnauthorized();
    $this->get(route('patients.create'))->assertUnauthorized();
    $this->get(route('admissions.create'))->assertUnauthorized();
});

test('admin can reach patients and admissions routes directly', function () {
    $user = User::factory()->create(['hospital_id' => Hospital::factory()->create()->id]);
    $user->assignRole('admin');
    $this->actingAs($user);

    $this->get(route('patients.index'))->assertSuccessful();
    $this->get(route('patients.create'))->assertSuccessful();
    $this->get(route('admissions.create'))->assertSuccessful();
});
