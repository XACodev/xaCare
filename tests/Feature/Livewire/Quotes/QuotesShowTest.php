<?php
// tests/Feature/Livewire/Quotes/QuotesShowTest.php

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

test('view_total ve staff_fee, hospital_cost y total', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->givePermissionTo('surgeries.budget.view_total');

    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id, 'staff_fee' => 7000, 'hospital_cost' => 7500,
    ]);

    $this->actingAs($admin);

    Volt::test('qxlog.quotes.show', ['quote' => $quote])
        ->assertSee(number_format(7000, 2))
        ->assertSee(number_format(7500, 2))
        ->assertSee(number_format(14500, 2));
});

test('view_own asignado ve solo staff_fee, no hospital_cost ni total', function () {
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
        'staff_fee' => 7000, 'hospital_cost' => 7500,
    ]);

    $this->actingAs($staff);

    Volt::test('qxlog.quotes.show', ['quote' => $quote])
        ->assertSee(number_format(7000, 2))
        ->assertDontSee(number_format(7500, 2))
        ->assertDontSee(number_format(14500, 2));
});

test('view_own sin asignacion a la cirugia recibe 403', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $staff = User::factory()->create(['hospital_id' => $hospital->id]);
    $staff->givePermissionTo('surgeries.budget.view_own');

    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id, 'staff_fee' => 7000, 'hospital_cost' => 7500,
    ]);

    $this->actingAs($staff);

    Volt::test('qxlog.quotes.show', ['quote' => $quote])->assertForbidden();
});

test('manage puede marcar como emitida una cotizacion en draft', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->givePermissionTo(['surgeries.budget.view_total', 'surgeries.budget.manage']);

    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id, 'status' => 'draft',
    ]);

    $this->actingAs($admin);

    Volt::test('qxlog.quotes.show', ['quote' => $quote])
        ->call('issue')
        ->assertHasNoErrors();

    expect($quote->fresh()->status)->toBe('issued');
});
