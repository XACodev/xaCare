<?php

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (Hospital::CORE_ROLES as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web', 'team_id' => null]);
    }
});

function makeHospitalAdmin(?Hospital $hospital = null): User
{
    $hospital ??= Hospital::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);
    $admin->assignRole('admin');

    return $admin;
}

test('a hospital admin can create a custom role', function () {
    $admin = makeHospitalAdmin();

    $this->actingAs($admin);

    Volt::test('settings.roles.index')
        ->set('new_role', 'Bodeguero Jefe')
        ->call('createRole')
        ->assertHasNoErrors();

    $role = Role::where('name', 'bodeguero_jefe')->where('guard_name', 'web')->first();

    expect($role)->not->toBeNull()
        ->and($role->team_id)->toBe($admin->hospital_id);
});

test('a hospital admin cannot create a role with a core name', function () {
    $admin = makeHospitalAdmin();

    $this->actingAs($admin);

    Volt::test('settings.roles.index')
        ->set('new_role', 'doctor')
        ->call('createRole')
        ->assertHasErrors(['new_role']);

    expect(Role::where('name', 'doctor')->where('team_id', $admin->hospital_id)->exists())->toBeFalse();
});

test('a hospital admin cannot create a duplicate role in their own hospital', function () {
    $admin = makeHospitalAdmin();
    Role::create(['name' => 'bodeguero', 'guard_name' => 'web', 'team_id' => $admin->hospital_id]);

    $this->actingAs($admin);

    Volt::test('settings.roles.index')
        ->set('new_role', 'bodeguero')
        ->call('createRole')
        ->assertHasErrors(['new_role']);

    expect(Role::where('name', 'bodeguero')->where('team_id', $admin->hospital_id)->count())->toBe(1);
});

test('a hospital admin cannot edit a role belonging to another hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    $adminA = makeHospitalAdmin($hospitalA);
    $foreignRole = Role::create(['name' => 'contador', 'guard_name' => 'web', 'team_id' => $hospitalB->id]);

    $this->actingAs($adminA);

    expect(fn () => Volt::test('settings.roles.index')->call('selectRole', $foreignRole->id))
        ->toThrow(ModelNotFoundException::class);
});

test('a hospital admin cannot delete a role that has users assigned', function () {
    $admin = makeHospitalAdmin();
    $role = Role::create(['name' => 'bodeguero', 'guard_name' => 'web', 'team_id' => $admin->hospital_id]);
    $staff = User::factory()->create(['role' => 'bodeguero', 'hospital_id' => $admin->hospital_id]);
    $staff->assignRole($role);

    $this->actingAs($admin);

    Volt::test('settings.roles.index')
        ->call('selectRole', $role->id)
        ->call('deleteRole')
        ->assertHasErrors(['delete']);

    expect(Role::find($role->id))->not->toBeNull();
});

test('a hospital admin from hospital B does not see custom roles from hospital A', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    Role::create(['name' => 'bodeguero', 'guard_name' => 'web', 'team_id' => $hospitalA->id]);
    $adminB = makeHospitalAdmin($hospitalB);

    $this->actingAs($adminB);

    Volt::test('settings.roles.index')
        ->assertDontSee('bodeguero');
});

test('super admin cannot access the hospital custom roles page', function () {
    $superAdmin = User::factory()->create(['is_platform_admin' => true, 'hospital_id' => null]);

    $this->actingAs($superAdmin)
        ->get(route('settings.roles.index'))
        ->assertForbidden();
});

test('a hospital admin can only assign existing permissions to a custom role', function () {
    $admin = makeHospitalAdmin();
    $validPermission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'procedures.view', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'bodeguero', 'guard_name' => 'web', 'team_id' => $admin->hospital_id]);

    $this->actingAs($admin);

    Volt::test('settings.roles.index')
        ->call('selectRole', $role->id)
        ->set('selected_permissions', ['procedures.view', 'nonexistent.permission'])
        ->call('saveRole')
        ->assertHasNoErrors();

    $role->refresh();
    expect($role->permissions->pluck('name')->toArray())->toContain('procedures.view')
        ->and($role->permissions->pluck('name')->toArray())->not->toContain('nonexistent.permission');
});
