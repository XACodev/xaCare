<?php

use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

test('super admin creating a role from the panel creates it as global', function () {
    $superAdmin = User::factory()->create(['is_platform_admin' => true, 'hospital_id' => null]);

    $this->actingAs($superAdmin);

    Volt::test('platform.roles.index')
        ->set('new_role', 'anesthesiologist')
        ->call('createRole')
        ->assertHasNoErrors();

    $role = Role::where('name', 'anesthesiologist')->where('guard_name', 'web')->first();

    expect($role)->not->toBeNull()
        ->and($role->team_id)->toBeNull();
});

test('the roles panel only lists global roles, never a hospital custom role', function () {
    $hospital = Hospital::factory()->create();
    Role::create(['name' => 'bodeguero', 'guard_name' => 'web', 'team_id' => $hospital->id]);
    Role::create(['name' => 'anesthesiologist', 'guard_name' => 'web', 'team_id' => null]);

    $superAdmin = User::factory()->create(['is_platform_admin' => true, 'hospital_id' => null]);
    $this->actingAs($superAdmin);

    $roleNames = collect(Volt::test('platform.roles.index')->get('roles'))->pluck('name');

    expect($roleNames)->toContain('anesthesiologist')
        ->and($roleNames)->not->toContain('bodeguero');
});

test('the refreshed roles list after creating a role still excludes hospital custom roles', function () {
    $hospital = Hospital::factory()->create();
    Role::create(['name' => 'bodeguero', 'guard_name' => 'web', 'team_id' => $hospital->id]);

    $superAdmin = User::factory()->create(['is_platform_admin' => true, 'hospital_id' => null]);
    $this->actingAs($superAdmin);

    $roleNames = collect(
        Volt::test('platform.roles.index')
            ->set('new_role', 'anesthesiologist')
            ->call('createRole')
            ->get('roles')
    )->pluck('name');

    expect($roleNames)->toContain('anesthesiologist')
        ->and($roleNames)->not->toContain('bodeguero');
});
