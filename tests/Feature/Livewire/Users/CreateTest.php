<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Livewire\Volt\Volt;

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

test('non super admin cannot view user creation page', function () {
    $user = User::factory()->create(['is_super_admin' => false, 'role' => 'admin']);

    $this->actingAs($user)
        ->get(route('users.create'))
        ->assertForbidden();
});

test('can create user with role', function () {
    $user = User::factory()->create(['is_super_admin' => true]);
    $role = Role::create(['name' => 'manager', 'guard_name' => 'web']);

    $this->actingAs($user);

    Volt::test('users.create')
        ->set('name', 'Test User')
        ->set('username', 'testuser')
        ->set('email', 'test@example.com')
        ->set('phone', '12345678')
        // The component binds `role` to the SELECT value which is the ID
        ->set('role', $role->name)
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('save')
        ->assertHasNoErrors();

    $createdUser = User::where('email', 'test@example.com')->first();
    expect($createdUser)->not->toBeNull();
    expect($createdUser->phone)->toBe('12345678');
    // The component sets the `role` column to the role NAME
    expect($createdUser->role)->toBe('manager');
    // And assigns the Spatie role
    expect($createdUser->hasRole('manager'))->toBeTrue();
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
