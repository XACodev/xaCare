<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CreateSA extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-s-a';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a super admin user';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $email = env('SA_EMAIL');
        $name = env('SA_NAME');
        $username = env('SA_USERNAME');
        $password = env('SA_PASSWORD');
        $role = env('SA_ROLE');

        if (empty($email) || empty($name) || empty($username) || empty($password) || empty($role)) {
            $this->error('Missing environment variables.');
            return;
        }

        $sa = User::firstOrCreate([
            'username' => $username,
        ], [
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'role' => $role,
            'phone' => env('SA_PHONE'),
            'is_platform_admin' => env('SA_SA'),
            'use_pay_scheme' => false,
        ]);

        $this->info("User {$sa->username} created successfully.");
    }
}
