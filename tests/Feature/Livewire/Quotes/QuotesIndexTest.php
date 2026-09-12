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

test('con solo permiso manage (sin view_total ni view_own) accede al indice sin 403', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->givePermissionTo('surgeries.budget.manage');

    $this->actingAs($admin);

    Volt::test('qxlog.quotes.index')->assertOk();
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

test('el buscador encuentra por nombre de paciente sin importar tildes', function () {
    $hospital = Hospital::factory()->create();
    $admin = manageAdmin($hospital);
    $patient = Patient::factory()->for($hospital, 'hospital')->create(['primer_nombre' => 'María', 'primer_apellido' => 'Xitumul']);
    SurgeryQuote::factory()->for($hospital, 'hospital')->create(['patient_id' => $patient->id]);
    $this->actingAs($admin);

    Volt::test('qxlog.quotes.index')
        ->set('search', 'maria')
        ->assertSee('María Xitumul');
});

test('el buscador encuentra por folio', function () {
    $hospital = Hospital::factory()->create();
    $admin = manageAdmin($hospital);
    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create();
    $this->actingAs($admin);

    Volt::test('qxlog.quotes.index')
        ->set('search', $quote->slug)
        ->assertSee($quote->slug);
});

test('cotizaciones con 30 o mas dias muestran badge de antiguedad', function () {
    $hospital = Hospital::factory()->create();
    $admin = manageAdmin($hospital);
    $old = SurgeryQuote::factory()->for($hospital, 'hospital')->create(['created_at' => now()->subDays(31)]);
    $recent = SurgeryQuote::factory()->for($hospital, 'hospital')->create(['created_at' => now()->subDays(2)]);
    $this->actingAs($admin);

    $component = Volt::test('qxlog.quotes.index');
    $component->assertSeeHtml('data-stale="'.$old->id.'"');
    $component->assertDontSeeHtml('data-stale="'.$recent->id.'"');
});

test('eliminar una cotizacion la quita del indice (soft delete)', function () {
    $hospital = Hospital::factory()->create();
    $admin = manageAdmin($hospital);
    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create();
    $this->actingAs($admin);

    Volt::test('qxlog.quotes.index')
        ->call('deleteQuote', $quote->id)
        ->assertDontSee($quote->slug);

    expect(SurgeryQuote::find($quote->id))->toBeNull();
    expect(SurgeryQuote::withTrashed()->find($quote->id))->not->toBeNull();
});

test('eliminar sin permiso manage falla', function () {
    $hospital = Hospital::factory()->create();
    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create();
    $viewer = User::factory()->create(['hospital_id' => $hospital->id]);
    $viewer->givePermissionTo('surgeries.budget.view_total');
    $this->actingAs($viewer);

    Volt::test('qxlog.quotes.index')->call('deleteQuote', $quote->id);

    expect(SurgeryQuote::find($quote->id))->not->toBeNull();
});
