<?php

namespace App\Support;

use App\Models\Hospital;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Crea los roles core NO globales (doctor, instrumentist, circulating) de un
 * hospital, con su set de permisos por defecto. `admin` queda fuera: sigue
 * siendo un rol global fijo (ver Fase A) y nunca se toca aquí.
 *
 * Idempotente: si el rol ya existe para el hospital no se le vuelve a
 * asignar el set por defecto (para no pisar personalizaciones que el
 * hospital ya haya hecho desde Roles Custom).
 */
class CoreRoleProvisioner
{
    /**
     * @var array<string, list<string>>
     */
    public const DEFAULT_PERMISSIONS = [
        'doctor' => ['procedures.create', 'procedures.view', 'surgeries.budget.view_own', 'search.appear_as_suggestion'],
        'instrumentist' => ['procedures.create', 'procedures.view', 'surgeries.budget.view_own', 'search.appear_as_suggestion'],
        'circulating' => ['procedures.create', 'procedures.view', 'surgeries.budget.view_own', 'search.appear_as_suggestion'],
    ];

    public static function provisionFor(Hospital $hospital): void
    {
        foreach (self::DEFAULT_PERMISSIONS as $roleName => $permissionNames) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'team_id' => $hospital->id,
            ]);

            if (! $role->wasRecentlyCreated) {
                continue;
            }

            foreach ($permissionNames as $permissionName) {
                Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
            }

            $role->givePermissionTo($permissionNames);
        }
    }
}
