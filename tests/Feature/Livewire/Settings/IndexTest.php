<?php

use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'settings.manage', 'guard_name' => 'web']);
});

test('admin can view the configurations hub', function () {
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => Hospital::factory()->create()->id]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('settings.manage');

    $this->actingAs($admin);

    Volt::test('settings.index')
        ->assertSee(__('Configurations'))
        ->assertSee(__('General Settings'));
});

test('non admin cannot access the configurations hub', function () {
    $user = User::factory()->create(['role' => 'staff', 'hospital_id' => Hospital::factory()->create()->id]);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertUnauthorized();
});

test('platform admin cannot access the hospital configurations hub', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'hospital_id' => Hospital::factory()->create()->id,
        'is_platform_admin' => true,
    ]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('settings.index'))
        ->assertForbidden();
});
