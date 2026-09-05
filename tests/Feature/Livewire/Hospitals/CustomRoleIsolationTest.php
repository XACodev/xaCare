<?php

use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (Hospital::CORE_ROLES as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web', 'team_id' => null]);
    }
});

test('a hospital admin cannot assign a custom role that belongs to another hospital via the UI', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    $adminA = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospitalA->id]);
    // Rol custom creado exclusivamente para el hospital B.
    Role::create(['name' => 'contador', 'guard_name' => 'web', 'team_id' => $hospitalB->id]);

    $this->actingAs($adminA);

    Volt::test('users.create')
        ->assertDontSee('contador')
        ->set('name', 'Sneaky')
        ->set('username', 'sneakyacct')
        ->set('email', 'sneakyacct@example.com')
        ->set('role', 'contador')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('save')
        ->assertHasErrors(['role']);

    expect(User::where('email', 'sneakyacct@example.com')->exists())->toBeFalse();
});

test('tampering availableRoles with another hospital custom role id does not bypass validation', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    $adminA = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospitalA->id]);
    $foreignRole = Role::create(['name' => 'contador', 'guard_name' => 'web', 'team_id' => $hospitalB->id]);

    $this->actingAs($adminA);

    Volt::test('users.create')
        ->set('availableRoles', [(string) $foreignRole->id => 'contador'])
        ->set('name', 'Sneaky')
        ->set('username', 'sneakyacct2')
        ->set('email', 'sneakyacct2@example.com')
        ->set('role', 'contador')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('save')
        ->assertHasErrors(['role']);

    expect(User::where('email', 'sneakyacct2@example.com')->exists())->toBeFalse();
});

test('a hospital admin editing staff cannot switch a user into another hospital custom role', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    $adminA = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospitalA->id]);
    $staff = User::factory()->create(['role' => 'doctor', 'hospital_id' => $hospitalA->id]);
    Role::create(['name' => 'contador', 'guard_name' => 'web', 'team_id' => $hospitalB->id]);

    $this->actingAs($adminA);

    Volt::test('users.edit', ['user' => $staff->id])
        ->assertDontSee('contador')
        ->set('role', 'contador')
        ->call('save')
        ->assertHasErrors(['role']);

    expect($staff->fresh()->hasRole('contador'))->toBeFalse();
});

test('two hospitals can each own a custom role with the same name without colliding', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $userA = User::factory()->create(['role' => 'bodeguero', 'hospital_id' => $hospitalA->id]);
    $userB = User::factory()->create(['role' => 'bodeguero', 'hospital_id' => $hospitalB->id]);

    expect(Role::where('name', 'bodeguero')->count())->toBe(2);

    $this->actingAs($userA);
    $userA->unsetRelation('roles')->unsetRelation('permissions');
    expect($userA->hasRole('bodeguero'))->toBeTrue();

    $this->actingAs($userB);
    $userB->unsetRelation('roles')->unsetRelation('permissions');
    expect($userB->hasRole('bodeguero'))->toBeTrue();

    // Cada usuario tiene asignado el objeto Role de SU PROPIO hospital, no el del otro,
    // aunque ambos compartan nombre.
    $roleOfHospitalA = Role::where('name', 'bodeguero')->where('team_id', $hospitalA->id)->firstOrFail();
    $roleOfHospitalB = Role::where('name', 'bodeguero')->where('team_id', $hospitalB->id)->firstOrFail();

    expect($userA->roles->pluck('id'))->toContain($roleOfHospitalA->id)
        ->and($userA->roles->pluck('id'))->not->toContain($roleOfHospitalB->id);

    // Re-asignar el rol por nombre desde el contexto de hospital B siempre resuelve el
    // objeto Role de B, nunca el de A, aunque compartan nombre.
    $this->actingAs($userB);
    $userB->assignRole('bodeguero');
    $userB->unsetRelation('roles')->unsetRelation('permissions');
    expect($userB->roles->pluck('id'))->toContain($roleOfHospitalB->id)
        ->and($userB->roles->pluck('id'))->not->toContain($roleOfHospitalA->id);
});

test('a hospital keeps its own custom role visible in users.create for its own admin', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    User::factory()->create(['role' => 'bodeguero', 'hospital_id' => $hospitalA->id]);
    User::factory()->create(['role' => 'bodeguero', 'hospital_id' => $hospitalB->id]);

    $adminA = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospitalA->id]);

    $this->actingAs($adminA);

    Volt::test('users.create')
        ->set('name', 'New Bodeguero')
        ->set('username', 'newbodeguero')
        ->set('email', 'newbodeguero@example.com')
        ->set('role', 'bodeguero')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('save')
        ->assertHasNoErrors();

    $created = User::where('email', 'newbodeguero@example.com')->firstOrFail();

    $this->actingAs($created);
    $created->unsetRelation('roles')->unsetRelation('permissions');
    expect($created->hasRole('bodeguero'))->toBeTrue();

    $roleOfHospitalA = Role::where('name', 'bodeguero')->where('team_id', $hospitalA->id)->firstOrFail();
    expect($created->roles->pluck('id'))->toContain($roleOfHospitalA->id);
});
