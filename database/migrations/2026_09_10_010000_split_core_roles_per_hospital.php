<?php

use App\Models\Hospital;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Fase B: doctor/instrumentist/circulating dejan de ser roles globales
     * (team_id null) y pasan a ser un rol por hospital (team_id = hospital_id),
     * editable desde Roles Custom igual que los roles a medida. `admin` no se
     * toca: sigue siendo global y fijo (Fase A).
     *
     * Por cada uno de esos 3 roles: se copia su set de permisos actual a un
     * rol nuevo por cada hospital existente, se reasignan las filas de
     * model_has_roles (la asignación real de usuarios, que YA está scopeada
     * por team_id = hospital_id vía Spatie Teams) al nuevo role_id, y al
     * final se borra el rol global — dejarlo junto al nuevo por-hospital
     * crearía una ambigüedad real: Role::findByName() de Spatie (usado
     * internamente por assignRole()/hasRole() con nombre string) hace
     * WHERE team_id IS NULL OR team_id = team_actual, así que con los dos
     * presentes el resultado que devuelva ->first() no es predecible.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['doctor', 'instrumentist', 'circulating'] as $roleName) {
            $globalRole = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->whereNull('team_id')
                ->first();

            if (! $globalRole) {
                continue;
            }

            $permissionNames = $globalRole->permissions()->pluck('name')->all();

            Hospital::query()->orderBy('id')->chunkById(100, function ($hospitals) use ($roleName, $globalRole, $permissionNames) {
                foreach ($hospitals as $hospital) {
                    $hospitalRole = Role::firstOrCreate([
                        'name' => $roleName,
                        'guard_name' => 'web',
                        'team_id' => $hospital->id,
                    ]);

                    if ($permissionNames) {
                        $hospitalRole->syncPermissions($permissionNames);
                    }

                    DB::table('model_has_roles')
                        ->where('role_id', $globalRole->id)
                        ->where('team_id', $hospital->id)
                        ->update(['role_id' => $hospitalRole->id]);
                }
            });

            // Cascada: borra sus filas en role_has_permissions y cualquier
            // model_has_roles que haya quedado sin reasignar (usuarios sin
            // hospital_id con este rol legacy, no debería haber en la práctica).
            $globalRole->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intencionalmente no reversible: reconstruir los roles globales
        // borrados perdería el historial de qué usuario tenía cuál permiso
        // personalizado por hospital. Restaurar desde backup si hace falta.
    }
};
