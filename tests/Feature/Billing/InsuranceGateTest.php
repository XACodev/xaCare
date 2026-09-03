<?php

use App\Enums\SubscriptionStatus;
use App\Models\Hospital;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('hospital staff cannot open the dashboard when the subscription is canceled', function () {
    $hospital = Hospital::factory()->create([
        'subscription_status' => SubscriptionStatus::Canceled,
    ]);
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'is_platform_admin' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('hospital staff can open the dashboard when the subscription is active', function () {
    $hospital = Hospital::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
    ]);
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'is_platform_admin' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('a basic-plan admin cannot open the insurance module', function () {
    $hospital = Hospital::factory()->create(['plan' => 'basic']);
    $admin = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'admin',
        'is_platform_admin' => false,
    ]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('modules.insurance'))
        ->assertForbidden();
});

test('a pro-plan admin can open the insurance module stub', function () {
    $hospital = Hospital::factory()->create();
    app(\App\Services\HospitalPlanService::class)->applyPlan($hospital, 'pro');

    $admin = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'admin',
        'is_platform_admin' => false,
    ]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('modules.insurance'))
        ->assertOk()
        ->assertSee('próximamente', false);
});
