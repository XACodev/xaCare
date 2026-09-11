<?php

use App\Models\Hospital;
use App\Support\CoreRoleProvisioner;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('admin global tiene view_total y manage', function () {
    $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'team_id' => null]);

    expect($admin->hasPermissionTo('surgeries.budget.view_total'))->toBeTrue();
    expect($admin->hasPermissionTo('surgeries.budget.manage'))->toBeTrue();
    expect($admin->hasPermissionTo('surgeries.budget.view_own'))->toBeFalse();
    expect($admin->hasPermissionTo('search.appear_as_suggestion'))->toBeFalse();
});

test('roles core de un hospital YA EXISTENTE (backfill) tienen view_own y search.appear_as_suggestion', function () {
    $hospital = Hospital::factory()->create();
    CoreRoleProvisioner::provisionFor($hospital);

    foreach (['doctor', 'instrumentist', 'circulating'] as $roleName) {
        $role = Role::where('name', $roleName)->where('team_id', $hospital->id)->first();
        expect($role)->not->toBeNull();
        expect($role->hasPermissionTo('surgeries.budget.view_own'))->toBeTrue();
        expect($role->hasPermissionTo('search.appear_as_suggestion'))->toBeTrue();
        expect($role->hasPermissionTo('surgeries.budget.view_total'))->toBeFalse();
        expect($role->hasPermissionTo('surgeries.budget.manage'))->toBeFalse();
    }
});

test('un hospital NUEVO creado despues de la migracion tambien recibe los permisos por defecto', function () {
    $hospital = Hospital::factory()->create();

    $doctor = Role::where('name', 'doctor')->where('team_id', $hospital->id)->first();

    expect($doctor->hasPermissionTo('surgeries.budget.view_own'))->toBeTrue();
    expect($doctor->hasPermissionTo('search.appear_as_suggestion'))->toBeTrue();
});
