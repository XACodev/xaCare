<?php

use App\Models\Hospital;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('a hospital without qxlog feature cannot access qxlog admin routes', function () {
    $hospital = Hospital::factory()->create([
        'features' => [],
    ]);
    $admin = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'admin',
        'is_platform_admin' => false,
    ]);
    $admin->assignRole('admin');

    $actingAs = $this->actingAs($admin);

    $actingAs->get(route('procedures.index'))->assertForbidden();
    $actingAs->get(route('procedures.create'))->assertForbidden();
    $actingAs->get(route('payouts.index'))->assertForbidden();
    $actingAs->get(route('payouts.create'))->assertForbidden();
    $actingAs->get(route('pricing.settings'))->assertForbidden();
    $actingAs->get(route('pricing.instrumentists'))->assertForbidden();
});

test('a hospital without qxlog feature cannot access instrumentist routes', function () {
    $hospital = Hospital::factory()->create([
        'features' => [],
    ]);
    $instrumentist = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'instrumentist',
        'is_platform_admin' => false,
    ]);

    $this->actingAs($instrumentist)
        ->get(route('instrumentist.payouts'))
        ->assertForbidden();
});

test('a basic-plan admin can access qxlog routes', function () {
    $hospital = Hospital::factory()->create(['plan' => 'basic']);
    $admin = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'admin',
        'is_platform_admin' => false,
    ]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('procedures.index'))
        ->assertOk();
});

test('a platform admin bypasses the qxlog feature gate', function () {
    $hospital = Hospital::factory()->create([
        'features' => [],
    ]);
    $platformAdmin = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'admin',
        'is_platform_admin' => true,
    ]);
    $platformAdmin->assignRole('admin');

    $this->actingAs($platformAdmin)
        ->get(route('procedures.index'))
        ->assertOk();
});
