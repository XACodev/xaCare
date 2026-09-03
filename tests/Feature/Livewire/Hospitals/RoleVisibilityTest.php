<?php

use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (\App\Models\Hospital::CORE_ROLES as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

test('a new non-core role is invisible to every hospital until enabled', function () {
    Role::create(['name' => 'anesthesiologist', 'guard_name' => 'web']);
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);

    $this->actingAs($admin);

    Volt::test('users.create')
        ->assertDontSee('anesthesiologist');
});

test('super admin enabling a role for one hospital does not affect another', function () {
    Role::create(['name' => 'anesthesiologist', 'guard_name' => 'web']);
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    $superAdmin = User::factory()->create(['is_super_admin' => true, 'hospital_id' => null]);

    $this->actingAs($superAdmin);

    Volt::test('hospitals.edit', ['hospital' => $hospitalA->id])
        ->call('toggleRole', 'anesthesiologist');

    expect($hospitalA->fresh()->enabled_roles)->toBe(['anesthesiologist']);
    expect($hospitalB->fresh()->enabled_roles ?? [])->toBe([]);

    $adminA = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospitalA->id]);
    $adminB = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospitalB->id]);

    $this->actingAs($adminA);
    Volt::test('users.create')->assertSee('anesthesiologist');

    $this->actingAs($adminB);
    Volt::test('users.create')->assertDontSee('anesthesiologist');
});

test('core roles are always visible without being enabled explicitly', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);

    $this->actingAs($admin);

    Volt::test('users.create')
        ->assertSee('admin')
        ->assertSee('doctor')
        ->assertSee('instrumentist')
        ->assertSee('circulating');
});

test('a hospital admin cannot assign a role that is not visible to their hospital', function () {
    Role::create(['name' => 'anesthesiologist', 'guard_name' => 'web']);
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);

    $this->actingAs($admin);

    Volt::test('users.create')
        ->set('name', 'Sneaky')
        ->set('username', 'sneaky1')
        ->set('email', 'sneaky1@example.com')
        ->set('role', 'anesthesiologist')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('save')
        ->assertHasErrors(['role']);
});
