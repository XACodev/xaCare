<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('patients index links each row to its detail view', function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);

    $this->actingAs($admin)
        ->get(route('patients.index'))
        ->assertOk()
        ->assertSee(route('patients.show', $patient), false);
});

test('super admin can view the patients index', function () {
    $superAdmin = User::factory()->create(['is_platform_admin' => true, 'hospital_id' => null]);

    $this->actingAs($superAdmin)
        ->get(route('patients.index'))
        ->assertOk();
});

test('instrumentist cannot view the patients index', function () {
    $user = User::factory()->create(['role' => 'instrumentist']);

    $this->actingAs($user)
        ->get(route('patients.index'))
        ->assertUnauthorized();
});
