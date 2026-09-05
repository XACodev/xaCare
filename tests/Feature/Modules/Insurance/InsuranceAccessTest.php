<?php

use App\Models\Hospital;
use App\Models\User;
use App\Services\HospitalPlanService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('a pro-plan admin can access /seguros', function () {
    $hospital = Hospital::factory()->create();
    app(HospitalPlanService::class)->applyPlan($hospital, 'pro');

    $admin = User::factory()->create([
        'hospital_id' => $hospital->id,
        'is_platform_admin' => false,
    ]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('modules.insurance'))
        ->assertOk()
        ->assertSee('Aseguradoras', false);
});

test('a basic-plan admin gets forbidden on /seguros', function () {
    $hospital = Hospital::factory()->create(['plan' => 'basic']);

    $admin = User::factory()->create([
        'hospital_id' => $hospital->id,
        'is_platform_admin' => false,
    ]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('modules.insurance'))
        ->assertForbidden();
});
