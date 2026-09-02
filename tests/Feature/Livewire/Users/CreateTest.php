<?php

use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Ensure roles table is clean or migrated, though RefreshDatabase should handle it.
    // We'll create necessary roles for each test.
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

test('super admin can view user creation page', function () {
    $user = User::factory()->create(['is_super_admin' => true]);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'doctor', 'guard_name' => 'web']);

    $this->actingAs($user)
        ->get(route('users.create'))
        ->assertSuccessful()
        ->assertSee(__('New User'));
});

test('non admin non super admin cannot view user creation page', function () {
    $user = User::factory()->create(['is_super_admin' => false, 'role' => 'instrumentist']);

    $this->actingAs($user)
        ->get(route('users.create'))
        ->assertForbidden();
});

test('hospital admin can view user creation page', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['is_super_admin' => false, 'role' => 'admin', 'hospital_id' => $hospital->id]);

    $this->actingAs($admin)
        ->get(route('users.create'))
        ->assertSuccessful()
        ->assertSee(__('New User'));
});

test('hospital admin creates staff scoped to their own hospital without choosing one', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['is_super_admin' => false, 'role' => 'admin', 'hospital_id' => $hospital->id]);
    $role = Role::create(['name' => 'instrumentist', 'guard_name' => 'web']);

    $this->actingAs($admin);

    Volt::test('users.create')
        ->set('name', 'Staff User')
        ->set('username', 'staffuser')
        ->set('email', 'staff@example.com')
        ->set('role', $role->name)
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('save')
        ->assertHasNoErrors();

    $created = User::where('email', 'staff@example.com')->first();
    expect($created)->not->toBeNull();
    expect($created->hospital_id)->toBe($hospital->id);
    expect($created->is_super_admin)->toBeFalse();
});

test('hospital admin cannot escalate a created user to super admin or another hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = User::factory()->create(['is_super_admin' => false, 'role' => 'admin', 'hospital_id' => $hospital->id]);
    $role = Role::create(['name' => 'instrumentist', 'guard_name' => 'web']);

    $this->actingAs($admin);

    Volt::test('users.create')
        ->set('name', 'Sneaky User')
        ->set('username', 'sneakyuser')
        ->set('email', 'sneaky@example.com')
        ->set('role', $role->name)
        ->set('is_super_admin', true)
        ->set('hospital_id', $otherHospital->id)
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('save')
        ->assertHasNoErrors();

    $created = User::where('email', 'sneaky@example.com')->first();
    expect($created->is_super_admin)->toBeFalse();
    expect($created->hospital_id)->toBe($hospital->id);
});

test('can create user with role', function () {
    $user = User::factory()->create(['is_super_admin' => true]);
    $role = Role::create(['name' => 'manager', 'guard_name' => 'web']);
    $hospital = Hospital::factory()->create();

    $this->actingAs($user);

    Volt::test('users.create')
        ->set('name', 'Test User')
        ->set('username', 'testuser')
        ->set('email', 'test@example.com')
        ->set('phone', '12345678')
        // The component binds `role` to the SELECT value which is the ID
        ->set('role', $role->name)
        ->set('hospital_id', $hospital->id)
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('save')
        ->assertHasNoErrors();

    $createdUser = User::withoutGlobalScopes()->where('email', 'test@example.com')->first();
    expect($createdUser)->not->toBeNull();
    // The component sets the `role` column to the role NAME
    expect($createdUser->role)->toBe('manager');
    expect($createdUser->hospital_id)->toBe($hospital->id);
    // And assigns the Spatie role
    expect($createdUser->hasRole('manager'))->toBeTrue();
});

test('creating a non super admin user requires a hospital', function () {
    $user = User::factory()->create(['is_super_admin' => true]);
    Role::create(['name' => 'manager', 'guard_name' => 'web']);

    $this->actingAs($user);

    Volt::test('users.create')
        ->set('name', 'Test User')
        ->set('username', 'testuser2')
        ->set('email', 'test2@example.com')
        ->set('role', 'manager')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('save')
        ->assertHasErrors(['hospital_id']);
});

test('validation requires role', function () {
    $user = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($user);

    Volt::test('users.create')
        ->set('name', 'Test User')
        ->set('username', 'testuser')
        ->set('email', 'test@example.com')
        ->set('role', '') // Empty role
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('save')
        ->assertHasErrors(['role']);
});
