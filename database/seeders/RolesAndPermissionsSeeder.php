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
        $instrumentistRole = Role::firstOrCreate(['name' => 'instrumentist', 'guard_name' => 'web', 'team_id' => null]);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web', 'team_id' => null]);
        $circulatingRole = Role::firstOrCreate(['name' => 'circulating', 'guard_name' => 'web', 'team_id' => null]);

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
        ]);

        $instrumentistRole->givePermissionTo([
            'procedures.create',
            'procedures.view',
        ]);

        $doctorRole->givePermissionTo([
            'procedures.create',
            'procedures.view',
        ]);

        $circulatingRole->givePermissionTo([
            'procedures.create',
            'procedures.view',
        ]);

        if ($wasExplicit) {
            setPermissionsTeamId($previousTeamId);
        } else {
            PermissionTeamResolver::clearExplicitTeamId();
        }

        $this->assignRoleToUsersWithLegacyRole('admin', $adminRole);
        $this->assignRoleToUsersWithLegacyRole('instrumentist', $instrumentistRole);
        $this->assignRoleToUsersWithLegacyRole('doctor', $doctorRole);
        $this->assignRoleToUsersWithLegacyRole('circulating', $circulatingRole);
    }

    private function assignRoleToUsersWithLegacyRole(string $legacyRole, Role $role): void
    {
        User::query()
            ->where('role', $legacyRole)
            ->get()
            ->each(function (User $user) use ($role): void {
                $user->assignRole($role);
            });
    }
}
