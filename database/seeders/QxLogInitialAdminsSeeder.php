<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class QxLogInitialAdminsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
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

        User::firstOrCreate(
            ['username' => 'admin_hospital'],
            [
                'name' => 'Administrador Hospital',
                'username' => 'hospital',
                'email' => 'hospitalcoban@gmail.com',
                'phone' => 77903000,
                'role' => 'admin',
                'is_platform_admin' => false,
                'use_pay_scheme' => false,
                'password' => Hash::make('1981'),
            ]
        );
    }
}
