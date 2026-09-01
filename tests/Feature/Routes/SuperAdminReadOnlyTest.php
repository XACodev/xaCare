<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'payouts.create', 'guard_name' => 'web']);
    $adminRole->givePermissionTo(['payouts.create']);
});

function makeSuperAdmin(): User
{
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_super_admin' => true, 'role' => 'admin']);
    $superAdmin->assignRole('admin');

    return $superAdmin;
}

test('super admin cannot register a patient', function () {
    $this->actingAs(makeSuperAdmin())
        ->get(route('patients.create'))
        ->assertForbidden();
});

test('super admin cannot register an admission', function () {
    $this->actingAs(makeSuperAdmin())
        ->get(route('admissions.create'))
        ->assertForbidden();
});

test('super admin cannot register a procedure', function () {
    $this->actingAs(makeSuperAdmin())
        ->get(route('procedures.create'))
        ->assertForbidden();
});

test('super admin cannot create a payout', function () {
    $this->actingAs(makeSuperAdmin())
        ->get(route('payouts.create'))
        ->assertForbidden();
});
