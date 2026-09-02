<?php

use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    // Seed roles needed for tests
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'manager', 'guard_name' => 'web']);
    Role::create(['name' => 'doctor', 'guard_name' => 'web']);
});

test('super admin can view user edit page', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $userToEdit = User::factory()->create(['role' => 'doctor']);
    // Assign role to userToEdit to avoid inconsistencies
    $userToEdit->assignRole('doctor');

    $this->actingAs($admin)
        ->get(route('users.edit', $userToEdit))
        ->assertSuccessful()
        ->assertSee(__('Edit User'))
        ->assertSee($userToEdit->name);
});

test('non admin non super admin cannot view user edit page', function () {
    $user = User::factory()->create(['is_super_admin' => false, 'role' => 'instrumentist']);
    $userToEdit = User::factory()->create();

    $this->actingAs($user)
        ->get(route('users.edit', $userToEdit))
        ->assertForbidden();
});

test('hospital admin can view and edit a user from their own hospital', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['is_super_admin' => false, 'role' => 'admin', 'hospital_id' => $hospital->id]);
    $userToEdit = User::factory()->create(['role' => 'doctor', 'hospital_id' => $hospital->id]);
    $userToEdit->assignRole('doctor');

    $this->actingAs($admin)
        ->get(route('users.edit', $userToEdit))
        ->assertSuccessful()
        ->assertSee(__('Edit User'));
});

test('hospital admin cannot reach a user from another hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = User::factory()->create(['is_super_admin' => false, 'role' => 'admin', 'hospital_id' => $hospital->id]);
    $userInOtherHospital = User::factory()->create(['hospital_id' => $otherHospital->id]);

    $this->actingAs($admin)
        ->get(route('users.edit', $userInOtherHospital))
        ->assertNotFound();
});

test('hospital admin cannot promote a user to super admin or move them to another hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = User::factory()->create(['is_super_admin' => false, 'role' => 'admin', 'username' => 'adminstaff', 'hospital_id' => $hospital->id]);
    // Username explícito: fake()->userName() a veces genera algo con punto (ej.
    // "jane.doe23"), que no pasa la regla alpha_dash del formulario y vuelve este test
    // intermitente sin relación con lo que se está probando.
    $userToEdit = User::factory()->create(['name' => 'Staff', 'username' => 'staffdoctor', 'role' => 'doctor', 'hospital_id' => $hospital->id]);
    $userToEdit->assignRole('doctor');

    $this->actingAs($admin);

    Volt::test('users.edit', ['user' => $userToEdit->id])
        ->set('is_super_admin', true)
        ->set('hospital_id', $otherHospital->id)
        ->call('save')
        ->assertHasNoErrors();

    $userToEdit->refresh();
    expect($userToEdit->is_super_admin)->toBeFalse();
    expect($userToEdit->hospital_id)->toBe($hospital->id);
});

test('can update user details and role', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $userToEdit = User::factory()->create(['name' => 'Old Name', 'role' => 'doctor', 'hospital_id' => Hospital::factory()->create()->id]);
    $userToEdit->assignRole('doctor');

    $this->actingAs($admin);

    Volt::test('users.edit', ['user' => $userToEdit->id])
        ->set('name', 'New Name')
        ->set('username', 'newusername')
        ->set('email', 'new@example.com')
        ->set('role', 'manager') // Changing role
        ->call('save')
        ->assertHasNoErrors();

    $userToEdit->refresh();

    expect($userToEdit->name)->toBe('New Name');
    expect($userToEdit->email)->toBe('new@example.com');
    expect($userToEdit->role)->toBe('manager');
    expect($userToEdit->hasRole('manager'))->toBeTrue();
    expect($userToEdit->hasRole('doctor'))->toBeFalse();
});

test('validation prevents duplicate email on update', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $userToEdit = User::factory()->create();
    $otherUser = User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($admin);

    Volt::test('users.edit', ['user' => $userToEdit->id])
        ->set('email', 'taken@example.com')
        ->call('save')
        ->assertHasErrors(['email']);
});

test('can soft delete and restore user', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $userToEdit = User::factory()->create();

    $this->actingAs($admin);

    // Delete
    Volt::test('users.edit', ['user' => $userToEdit->id])
        ->call('toggleDelete');

    expect($userToEdit->fresh()->deleted_at)->not->toBeNull();

    // Restore
    Volt::test('users.edit', ['user' => $userToEdit->id])
        ->call('toggleDelete');

    expect($userToEdit->fresh()->deleted_at)->toBeNull();
});

test('cannot delete self', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($admin);

    Volt::test('users.edit', ['user' => $admin->id])
        ->call('toggleDelete')
        ->assertForbidden();
});
