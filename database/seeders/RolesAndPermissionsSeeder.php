<?php

namespace Database\Seeders;

use App\Auth\PermissionTeamResolver;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()['cache']->forget('spatie.permission.cache');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $previousTeamId = getPermissionsTeamId();
        $wasExplicit = PermissionTeamResolver::hasExplicitTeamId();
        setPermissionsTeamId(null);

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'team_id' => null]);

        $permissions = [
            'procedures.create',
            'procedures.view',
            'procedures.edit',
            'payouts.create',
            'payouts.view',
            'pricing.manage',
            'settings.manage',
            'users.manage',
            'roles.manage',
            'surgeries.view',
            'surgeries.schedule',
            'surgeries.cancel',
            'surgeries.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $adminRole->givePermissionTo([
            'procedures.create',
            'procedures.view',
            'procedures.edit',
            'payouts.create',
            'payouts.view',
            'pricing.manage',
            'settings.manage',
            'surgeries.view',
            'surgeries.schedule',
            'surgeries.cancel',
            'surgeries.delete',
        ]);

        if ($wasExplicit) {
            setPermissionsTeamId($previousTeamId);
        } else {
            PermissionTeamResolver::clearExplicitTeamId();
        }

        User::query()
            ->where('role', 'admin')
            ->get()
            ->each(fn (User $user) => $user->assignRole($adminRole));

        foreach (['instrumentist', 'doctor', 'circulating'] as $legacyRole) {
            $this->assignHospitalScopedLegacyRole($legacyRole);
        }
    }

    /**
     * doctor/instrumentist/circulating son un rol por hospital (Fase B): cada
     * usuario recibe el rol correspondiente a su propio hospital, no uno global.
     */
    private function assignHospitalScopedLegacyRole(string $legacyRole): void
    {
        User::query()
            ->where('role', $legacyRole)
            ->whereNotNull('hospital_id')
            ->get()
            ->groupBy('hospital_id')
            ->each(function ($users, $hospitalId) use ($legacyRole): void {
                $role = Role::query()
                    ->where('name', $legacyRole)
                    ->where('guard_name', 'web')
                    ->where('team_id', $hospitalId)
                    ->first();

                if (! $role) {
                    return;
                }

                $users->each(fn (User $user) => $user->assignRole($role));
            });
    }
}
