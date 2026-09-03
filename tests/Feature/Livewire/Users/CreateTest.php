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

test('super admin can view user creation page for a specific hospital', function () {
    $user = User::factory()->create(['is_platform_admin' => true, 'hospital_id' => null]);
    $hospital = Hospital::factory()->create();
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'doctor', 'guard_name' => 'web']);

    $this->actingAs($user)
        ->get(route('users.create', ['hospital_id' => $hospital->id]))
        ->assertSuccessful()
        ->assertSee(__('New User'));
});

test('super admin cannot view user creation page without a hospital_id', function () {
    $user = User::factory()->create(['is_platform_admin' => true, 'hospital_id' => null]);

    $this->actingAs($user)
        ->get(route('users.create'))
        ->assertNotFound();
});

test('non admin non super admin cannot view user creation page', function () {
    $user = User::factory()->create(['is_platform_admin' => false, 'role' => 'instrumentist']);

    $this->actingAs($user)
        ->get(route('users.create'))
        ->assertForbidden();
});

test('hospital admin can view user creation page', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['is_platform_admin' => false, 'role' => 'admin', 'hospital_id' => $hospital->id]);

    $this->actingAs($admin)
        ->get(route('users.create'))
        ->assertSuccessful()
        ->assertSee(__('New User'));
});

test('hospital admin creates staff scoped to their own hospital without choosing one', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['is_platform_admin' => false, 'role' => 'admin', 'hospital_id' => $hospital->id]);
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
    expect($created->is_platform_admin)->toBeFalse();
});

test('hospital admin cannot move a created user to another hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = User::factory()->create(['is_platform_admin' => false, 'role' => 'admin', 'hospital_id' => $hospital->id]);
    $role = Role::create(['name' => 'instrumentist', 'guard_name' => 'web']);

    $this->actingAs($admin);

    // El campo hospital_id ni siquiera está en el formulario para un admin de hospital,
    // pero igual se manipula la propiedad Livewire directamente (equivalente a manipular
    // el request) para confirmar que save() la fuerza de vuelta a su propio hospital.
    Volt::test('users.create')
        ->set('name', 'Sneaky User')
        ->set('username', 'sneakyuser')
        ->set('email', 'sneaky@example.com')
        ->set('role', $role->name)
        ->set('hospital_id', $otherHospital->id)
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('save')
        ->assertHasNoErrors();

    $created = User::where('email', 'sneaky@example.com')->first();
    expect($created->is_platform_admin)->toBeFalse();
    expect($created->hospital_id)->toBe($hospital->id);
});

test('validation requires role', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['is_platform_admin' => false, 'role' => 'admin', 'hospital_id' => $hospital->id]);

    $this->actingAs($admin);

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

test('hospital admin without hospital_id gets a clear 422 error', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create([
        'is_platform_admin' => false,
        'role' => 'admin',
        'hospital_id' => null,
    ]));

    $this->actingAs($admin);

    Volt::test('users.create')
        ->assertStatus(422);
});
