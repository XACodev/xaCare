<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('seeder crea los permisos de programacion de cirugias sin asignarlos a ningun rol', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $permissions = ['surgeries.view', 'surgeries.schedule', 'surgeries.cancel', 'surgeries.delete'];

    foreach ($permissions as $name) {
        $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();
        expect($permission)->not->toBeNull();
    }

    foreach (Role::whereNull('team_id')->get() as $role) {
        foreach ($permissions as $name) {
            expect($role->hasPermissionTo($name))->toBeFalse();
        }
    }
});
