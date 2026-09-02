<?php

use App\Models\Hospital;
use App\Models\RoleRate;
use App\Models\SurgicalAssignment;
use App\Models\SurgicalCase;
use App\Models\SurgicalRole;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    foreach (['procedures.edit', 'pricing.manage'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $adminRole->givePermissionTo(['procedures.edit', 'pricing.manage']);
});

function makeFase1SuperAdmin(): User
{
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_super_admin' => true, 'role' => 'admin']);
    $superAdmin->assignRole('admin');

    return $superAdmin;
}

function makeFase1HospitalAdmin(?Hospital $hospital = null): User
{
    $hospital ??= Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'is_super_admin' => false, 'role' => 'admin']);
    $admin->assignRole('admin');

    return $admin;
}

test('super admin cannot edit (save) a SurgicalCase / SurgicalAssignment of another hospital', function () {
    $hospital = Hospital::factory()->create();
    $role = SurgicalRole::factory()->create(['hospital_id' => $hospital->id]);

    $case = SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'procedure_type' => 'Original',
    ]);
    $assignment = SurgicalAssignment::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_case_id' => $case->id,
        'surgical_role_id' => $role->id,
    ]);

    $superAdmin = makeFase1SuperAdmin();
    $this->actingAs($superAdmin);

    expect(fn () => $case->update(['procedure_type' => 'Tampered']))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    expect(fn () => $assignment->update(['note' => 'tampered']))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('super admin cannot delete a SurgicalCase', function () {
    $hospital = Hospital::factory()->create();
    $case = SurgicalCase::factory()->create(['hospital_id' => $hospital->id]);

    $superAdmin = makeFase1SuperAdmin();
    $this->actingAs($superAdmin);

    expect(fn () => $case->delete())
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('super admin cannot save/create a RoleRate', function () {
    $hospital = Hospital::factory()->create();
    $role = SurgicalRole::factory()->create(['hospital_id' => $hospital->id]);

    $superAdmin = makeFase1SuperAdmin();
    $this->actingAs($superAdmin);

    expect(fn () => RoleRate::create([
        'hospital_id' => $hospital->id,
        'surgical_role_id' => $role->id,
        'base_rate' => 999,
        'active' => true,
    ]))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('super admin cannot modify use_pay_scheme of a User via pricing.instrumentist toggle', function () {
    $hospital = Hospital::factory()->create();
    $target = User::factory()->create(['hospital_id' => $hospital->id, 'use_pay_scheme' => false]);

    $superAdmin = makeFase1SuperAdmin();
    $this->actingAs($superAdmin);

    Volt::test('pricing.instrumentist')
        ->call('toggle', $target->id)
        ->assertForbidden();

    expect($target->fresh()->use_pay_scheme)->toBeFalse();
});
