<?php

use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
});

test('super admin can view a user profile', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $userToView = User::factory()->create(['role' => 'doctor', 'phone' => '55550001']);
    $userToView->assignRole('doctor');

    $this->actingAs($admin)
        ->get(route('users.show', $userToView))
        ->assertOk()
        ->assertSee($userToView->name)
        ->assertSee($userToView->username)
        ->assertSee($userToView->phone);
});

test('the profile url is not the numeric id', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $userToView = User::factory()->create(['role' => 'doctor']);
    $userToView->assignRole('doctor');

    expect(route('users.show', $userToView))->not->toContain('/users/'.$userToView->id);

    $this->actingAs($admin)
        ->get('/users/'.$userToView->id)
        ->assertNotFound();
});

test('super admin can view the profile of a soft-deleted user', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $userToView = User::factory()->create(['role' => 'doctor']);
    $userToView->assignRole('doctor');
    $userToView->delete();

    $this->actingAs($admin)
        ->get(route('users.show', $userToView))
        ->assertOk()
        ->assertSee($userToView->name);
});

test('non super admin cannot view a user profile', function () {
    $user = User::factory()->create(['is_super_admin' => false, 'role' => 'instrumentist']);
    $user->assignRole('doctor');
    $userToView = User::factory()->create();

    $this->actingAs($user)
        ->get(route('users.show', $userToView))
        ->assertForbidden();
});
