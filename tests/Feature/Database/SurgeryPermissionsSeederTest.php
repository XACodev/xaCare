<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('seeder crea los permisos de programacion de cirugias y los otorga solo al rol admin', function () {
    // Decision de diseno (2026-09-10): admin siempre tiene el catalogo completo de
    // permisos de hospital (no hay forma en la UI de auto-otorgarselos de otro modo).
    // Los demas roles core (doctor, instrumentist, circulating) siguen sin surgeries.*
    // por defecto: el hospital los habilita explicitamente desde Roles Custom.
    $this->seed(RolesAndPermissionsSeeder::class);

    $permissions = ['surgeries.view', 'surgeries.schedule', 'surgeries.cancel', 'surgeries.delete'];

    foreach ($permissions as $name) {
        $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();
        expect($permission)->not->toBeNull();
    }

    $admin = Role::whereNull('team_id')->where('name', 'admin')->firstOrFail();
    foreach ($permissions as $name) {
        expect($admin->hasPermissionTo($name))->toBeTrue();
    }

    foreach (Role::whereNull('team_id')->where('name', '!=', 'admin')->get() as $role) {
        foreach ($permissions as $name) {
            expect($role->hasPermissionTo($name))->toBeFalse();
        }
    }
});
