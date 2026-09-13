<?php

use App\Models\Hospital;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'settings.manage', 'guard_name' => 'web']);
});

function admissionTypesSettingsAdmin(Hospital $hospital): User
{
    $admin = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'admin',
        'is_platform_admin' => false,
    ]);
    $admin->givePermissionTo('settings.manage');

    return $admin;
}

function hospitalWithCustomFormAddon(bool $enabled): Hospital
{
    $hospital = Hospital::factory()->create();

    if (! $enabled) {
        return $hospital;
    }

    if (\Illuminate\Support\Facades\Schema::hasColumn('hospitals', 'addons')) {
        $hospital->forceFill(['addons' => ['admissions_custom_form']])->save();
    } else {
        $hospital->update([
            'features' => array_values(array_unique([...($hospital->features ?? []), 'admissions_custom_form'])),
        ]);
    }

    return $hospital->fresh();
}

it('returns 403 for settings.admission-types without the admissions_custom_form addon', function () {
    $hospital = hospitalWithCustomFormAddon(false);
    $user = admissionTypesSettingsAdmin($hospital);

    $this->actingAs($user)->get(route('settings.admission-types'))->assertForbidden();
});

it('allows access to settings.admission-types with the admissions_custom_form addon', function () {
    $hospital = hospitalWithCustomFormAddon(true);
    $user = admissionTypesSettingsAdmin($hospital);

    $this->actingAs($user)->get(route('settings.admission-types'))->assertOk();
});
