<?php
// tests/Feature/Livewire/Quotes/QuotesPrintTest.php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\SurgeryQuote;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'surgeries.budget.view_total', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'surgeries.budget.view_own', 'guard_name' => 'web']);
});

test('la copia interna muestra el desglose de renglones', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create(['patient_id' => $patient->id]);
    $quote->syncLineItems([
        ['surgical_role_id' => null, 'label' => 'Cirujano', 'amount' => 3500],
    ]);
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $user->givePermissionTo('surgeries.budget.view_total');
    $this->actingAs($user);

    Volt::test('qxlog.quotes.print', ['quote' => $quote])
        ->assertSee('Cirujano')
        ->assertSee($quote->slug);
});

test('la copia paciente no incluye el desglose en el html', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create(['patient_id' => $patient->id]);
    $quote->syncLineItems([
        ['surgical_role_id' => null, 'label' => 'Cirujano', 'amount' => 3500],
    ]);
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $user->givePermissionTo('surgeries.budget.view_total');
    $this->actingAs($user);

    $html = Volt::test('qxlog.quotes.print', ['quote' => $quote])->set('view', 'patient')->html();

    expect($html)->not->toContain('Cirujano');
});

test('sin permiso view_total responde 403', function () {
    $hospital = Hospital::factory()->create();
    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    Volt::test('qxlog.quotes.print', ['quote' => $quote])->assertForbidden();
});

test('view_own no puede imprimir', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $staff = User::factory()->create(['hospital_id' => $hospital->id]);
    $staff->givePermissionTo('surgeries.budget.view_own');

    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create(['patient_id' => $patient->id]);

    $this->actingAs($staff);

    Volt::test('qxlog.quotes.print', ['quote' => $quote])->assertForbidden();
});

test('la copia interna muestra la nota interna y la copia paciente no', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id,
        'internal_note' => 'Paciente diabetico, verificar anestesiologo.',
    ]);
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $user->givePermissionTo('surgeries.budget.view_total');
    $this->actingAs($user);

    Volt::test('qxlog.quotes.print', ['quote' => $quote])
        ->assertSee('Paciente diabetico, verificar anestesiologo.');

    $html = Volt::test('qxlog.quotes.print', ['quote' => $quote])->set('view', 'patient')->html();

    expect($html)->not->toContain('Paciente diabetico, verificar anestesiologo.');
});
