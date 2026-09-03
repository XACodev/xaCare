<?php

use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'instrumentist', 'guard_name' => 'web']);
});

test('the staff section only lists users from this hospital, never super admins', function () {
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();

    $ownStaff = User::factory()->create(['name' => 'Own Staff', 'hospital_id' => $hospital->id]);
    $otherStaff = User::factory()->create(['name' => 'Other Hospital Staff', 'hospital_id' => $otherHospital->id]);
    $anotherSuperAdmin = User::factory()->create(['name' => 'Another Super Admin', 'hospital_id' => null, 'is_platform_admin' => true]);

    $this->actingAs($superAdmin);

    Volt::test('hospitals.edit', ['hospital' => $hospital->id])
        ->assertSee('Own Staff')
        ->assertDontSee('Other Hospital Staff')
        ->assertDontSee('Another Super Admin');
});

test('super admin can delete and restore staff from within the hospital page', function () {
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $hospital = Hospital::factory()->create();
    $staff = User::factory()->create(['hospital_id' => $hospital->id]);

    $this->actingAs($superAdmin);

    $component = Volt::test('hospitals.edit', ['hospital' => $hospital->id])
        ->call('deleteStaff', $staff->id);

    expect($staff->fresh()->deleted_at)->not->toBeNull();

    $component->call('restoreStaff', $staff->id);

    expect($staff->fresh()->deleted_at)->toBeNull();
});
