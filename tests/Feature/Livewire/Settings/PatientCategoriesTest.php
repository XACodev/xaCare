<?php

use App\Models\Hospital;
use App\Models\PatientCategory;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'settings.manage', 'guard_name' => 'web']);
});

test('hospital admin sees default patient categories and can rename one', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->assignRole('admin');
    $admin->givePermissionTo('settings.manage');

    PatientCategory::seedForHospital($hospital);

    $this->actingAs($admin);

    Volt::test('settings.patient-categories')
        ->assertSuccessful()
        ->assertSee('Recién nacido/a')
        ->assertSee('Adulto/a')
        ->call('startEdit', PatientCategory::where('hospital_id', $hospital->id)->where('code', 'nino')->first()->id, 'Niño/a')
        ->set('editingName', 'Niñez')
        ->call('saveName')
        ->assertHasNoErrors();

    expect(PatientCategory::where('hospital_id', $hospital->id)->where('code', 'nino')->first()->name)->toBe('Niñez');
});

test('hospital admin can deactivate a patient category', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->assignRole('admin');
    $admin->givePermissionTo('settings.manage');

    PatientCategory::seedForHospital($hospital);

    $this->actingAs($admin);

    $category = PatientCategory::where('hospital_id', $hospital->id)->where('code', 'adulto_mayor')->first();

    Volt::test('settings.patient-categories')
        ->call('toggleActive', $category->id)
        ->assertHasNoErrors();

    expect($category->fresh()->active)->toBeFalse();
});

test('admin without settings manage permission cannot access patient categories', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($admin);

    Volt::test('settings.patient-categories')->assertStatus(403);
});
