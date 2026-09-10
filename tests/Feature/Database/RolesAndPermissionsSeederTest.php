<?php

use App\Models\Hospital;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

test('roles and permissions seeder creates admin as a global role', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();

    expect($adminRole)->not->toBeNull()
        ->and($adminRole->team_id)->toBeNull();
});

test('doctor, instrumentist and circulating are provisioned per hospital, not global', function () {
    $hospital = Hospital::factory()->create();
    $this->seed(RolesAndPermissionsSeeder::class);

    foreach (['doctor', 'instrumentist', 'circulating'] as $roleName) {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->where('team_id', $hospital->id)->first();

        expect($role)->not->toBeNull();
    }

    expect(Role::where('name', 'doctor')->where('guard_name', 'web')->whereNull('team_id')->exists())->toBeFalse();
});

test('roles and permissions seeder assigns roles inside the user hospital team', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'instrumentist',
    ]);

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(DB::table('model_has_roles')->where('model_id', $user->id)->value('team_id'))
        ->toBe($hospital->id);

    $this->actingAs($user);
    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect($user->hasRole('instrumentist'))->toBeTrue();
});

test('roles and permissions seeder is idempotent', function () {
    $hospital = Hospital::factory()->create();
    User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'admin',
    ]);

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::where('name', 'admin')->where('guard_name', 'web')->count())->toBe(1)
        ->and(DB::table('model_has_roles')->count())->toBe(1);
});

test('roles and permissions seeder does not duplicate permissions when run twice', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(DB::table('permissions')->where('name', 'procedures.view')->count())->toBe(1);

    $admin = Role::where('name', 'admin')->first();

    expect($admin->permissions()->count())->toBe(11);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});
