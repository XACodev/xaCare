<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function loadGrantSurgeriesPermissionsToAdminMigration(): object
{
    return include database_path('migrations/2026_09_10_000000_grant_surgeries_permissions_to_admin.php');
}

test('otorga los permisos de cirugias al rol admin global existente', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'team_id' => null]);

    loadGrantSurgeriesPermissionsToAdminMigration()->up();

    $admin = Role::whereNull('team_id')->where('name', 'admin')->firstOrFail();

    foreach (['surgeries.view', 'surgeries.schedule', 'surgeries.cancel', 'surgeries.delete'] as $name) {
        expect($admin->hasPermissionTo($name))->toBeTrue();
    }
});

test('no falla si el rol admin todavia no existe', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::whereNull('team_id')->where('name', 'admin')->delete();

    loadGrantSurgeriesPermissionsToAdminMigration()->up();

    expect(Permission::where('name', 'surgeries.view')->exists())->toBeTrue();
});
