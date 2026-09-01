<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

        // Create roles
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
        $instrumentistRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'instrumentist']);
        $doctorRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'doctor']);
        $circulatingRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'circulating']);

        // Create permissions
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
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to admin role
        $adminRole->givePermissionTo([
            'procedures.create',
            'procedures.view',
            'procedures.edit',
            'payouts.create',
            'payouts.view',
            'pricing.manage',
            'settings.manage',
        ]);

        // Assign permissions to instrumentist role
        $instrumentistRole->givePermissionTo([
            'procedures.create',
            'procedures.view',
        ]);

        // Assign permissions to doctor role
        $doctorRole->givePermissionTo([
            'procedures.create',
            'procedures.view',
        ]);

        // Assign permissions to circulating role
        $circulatingRole->givePermissionTo([
            'procedures.create',
            'procedures.view',
        ]);

        // Assign roles to users
        $adminUser = User::where('role', 'admin')->get();
        $adminUser->each->assignRole($adminRole);

        $instrumentistUser = User::where('role', 'instrumentist')->get();
        $instrumentistUser->each->assignRole($instrumentistRole);

        $doctorUser = User::where('role', 'doctor')->get();
        $doctorUser->each->assignRole($doctorRole);

        $circulatingUser = User::where('role', 'circulating')->get();
        $circulatingUser->each->assignRole($circulatingRole);
    }
}
