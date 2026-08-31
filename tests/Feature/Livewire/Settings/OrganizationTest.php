<?php

use App\Models\OrganizationSetting;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'settings.manage', 'guard_name' => 'web']);
});

test('admin can view and save organization settings', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');
    $admin->givePermissionTo('settings.manage');

    $this->actingAs($admin);

    Volt::test('settings.organization')
        ->assertSet('org_name', OrganizationSetting::current()->org_name)
        ->set('org_name', 'Hospital de Prueba')
        ->set('voucher_legend', 'Leyenda de prueba')
        ->call('save')
        ->assertHasNoErrors();

    expect(OrganizationSetting::current()->org_name)->toBe('Hospital de Prueba');
});

test('admin without settings.manage permission cannot access the page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('settings.organization'))
        ->assertForbidden();
});
