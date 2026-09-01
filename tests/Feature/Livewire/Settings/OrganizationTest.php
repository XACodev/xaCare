<?php

use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

test('admin can upload an organization logo', function () {
    Storage::fake(OrganizationSetting::logoDisk());

    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');
    $admin->givePermissionTo('settings.manage');

    $this->actingAs($admin);

    $logo = UploadedFile::fake()->image('logo.png');

    Volt::test('settings.organization')
        ->set('logo', $logo)
        ->call('save')
        ->assertHasNoErrors();

    $path = OrganizationSetting::current()->logo_path;

    expect($path)->not->toBeNull();
    expect($path)->toEndWith('.webp');
    Storage::disk(OrganizationSetting::logoDisk())->assertExists($path);
});

test('admin can remove the organization logo', function () {
    Storage::fake(OrganizationSetting::logoDisk());

    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');
    $admin->givePermissionTo('settings.manage');

    $this->actingAs($admin);

    $existingPath = UploadedFile::fake()->image('logo.png')->store('org-logos', OrganizationSetting::logoDisk());
    OrganizationSetting::current()->update(['logo_path' => $existingPath]);

    Volt::test('settings.organization')
        ->call('removeLogo')
        ->assertHasNoErrors();

    expect(OrganizationSetting::current()->logo_path)->toBeNull();
    Storage::disk(OrganizationSetting::logoDisk())->assertMissing($existingPath);
});
