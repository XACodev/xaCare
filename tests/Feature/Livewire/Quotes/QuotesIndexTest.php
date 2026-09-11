<?php
// tests/Feature/Livewire/Quotes/QuotesIndexTest.php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\SurgeryQuote;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\SurgicalRole;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['surgeries.budget.view_total', 'surgeries.budget.view_own', 'surgeries.budget.manage'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
});

test('view_total ve todas las cotizaciones del hospital con monto total', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->givePermissionTo('surgeries.budget.view_total');

    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id, 'staff_fee' => 7000, 'hospital_cost' => 7000,
    ]);

    $this->actingAs($admin);

    Volt::test('qxlog.quotes.index')
        ->assertSee(number_format(14000, 2));
});

test('view_own solo ve cotizaciones de cirugias donde esta asignado, sin ver el total', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $staff = User::factory()->create(['hospital_id' => $hospital->id]);
    $staff->givePermissionTo('surgeries.budget.view_own');

    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create();
    $case = SurgicalCase::factory()->for($hospital, 'hospital')->create(['patient_id' => $patient->id]);
    SurgicalAssignment::factory()->for($hospital, 'hospital')->create([
        'surgical_case_id' => $case->id, 'surgical_role_id' => $role->id, 'user_id' => $staff->id,
    ]);

    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id, 'surgical_case_id' => $case->id,
        'staff_fee' => 7000, 'hospital_cost' => 7000,
    ]);

    $otherCase = SurgicalCase::factory()->for($hospital, 'hospital')->create(['patient_id' => $patient->id]);
    $otherQuote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id, 'surgical_case_id' => $otherCase->id,
        'staff_fee' => 100, 'hospital_cost' => 100,
    ]);

    $this->actingAs($staff);

    Volt::test('qxlog.quotes.index')
        ->assertSee(number_format(7000, 2))
        ->assertDontSee(number_format(14000, 2))
        ->assertDontSee(number_format(100, 2));
});

test('sin ningun permiso de presupuesto, el indice responde 403', function () {
    $hospital = Hospital::factory()->create();
    $staff = User::factory()->create(['hospital_id' => $hospital->id]);
    // El rol por defecto de la factory ('doctor') trae de serie
    // surgeries.budget.view_own (ver CoreRoleProvisioner), asi que para
    // probar el caso sin ningun permiso de presupuesto hay que despojar
    // al usuario de ese rol.
    $staff->syncRoles([]);
    $this->actingAs($staff);

    Volt::test('qxlog.quotes.index')->assertForbidden();
});
