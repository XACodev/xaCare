<?php

namespace Database\Seeders;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class QxLogInitialAdminsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $super = User::firstOrCreate(
            ['username' => 'thealejandro'],
            [
                'name' => 'Alejandro',
                'username' => 'thealejandro',
                'email' => 'thealejandro7w7@gmail.com',
                'phone' => 30683865,
                'role' => 'admin',
                'is_platform_admin' => true,
                'use_pay_scheme' => false,
                'password' => Hash::make('9977'),
            ]
        );

        $this->assignSpatieRole($super);

        $hospital = Hospital::query()->first();

        if ($hospital === null) {
            return;
        }

        $admin = User::firstOrCreate(
            ['username' => 'admin_hospital'],
            [
                'name' => 'Administrador Hospital',
                'username' => 'hospital',
                'email' => 'hospitalcoban@gmail.com',
                'phone' => 77903000,
                'role' => 'admin',
                'is_platform_admin' => false,
                'use_pay_scheme' => false,
                'hospital_id' => $hospital->id,
                'password' => Hash::make('1981'),
            ]
        );

        $this->assignSpatieRole($admin);
    }

    private function assignSpatieRole(User $user): void
    {
        $roleName = $user->role;

        if (! is_string($roleName) || $roleName === '') {
            return;
        }

        $teamId = in_array($roleName, Hospital::CORE_ROLES, true) ? null : $user->hospital_id;

        Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
            'team_id' => $teamId,
        ]);

        $user->assignRole($roleName);
    }
}
