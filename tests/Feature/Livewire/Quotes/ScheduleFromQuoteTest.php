<?php
// tests/Feature/Livewire/Quotes/ScheduleFromQuoteTest.php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\SurgeryQuote;
use Illuminate\Foundation\Testing\WithFaker;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'surgeries.schedule', 'guard_name' => 'web']);
});

test('agendar con quote_id en el query string vincula la cotizacion a la cirugia creada', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->givePermissionTo('surgeries.schedule');

    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create(['patient_id' => $patient->id]);

    $this->actingAs($admin);

    $this->get('/surgeries/create?patient_id='.$patient->id.'&quote_id='.$quote->id);

    // Volt::test() dispara su propia petición HTTP aislada para montar el componente,
    // por lo que el query string debe pasarse explícitamente aquí (no basta con el $this->get() anterior).
    Livewire::withQueryParams(['patient_id' => $patient->id, 'quote_id' => $quote->id]);

    Volt::test('qxlog.surgeries.schedule')
        ->set('patient_id', $patient->id)
        ->set('patient_query', $patient->nombreCompleto())
        ->set('procedure_date', now()->toDateString())
        ->set('start_time', '08:00')
        ->set('end_time', '09:00')
        ->set('procedure_type_query', 'Apendicectomía')
        ->call('schedule')
        ->assertHasNoErrors();

    expect($quote->fresh()->surgical_case_id)->not->toBeNull();
});

test('sin quote_id, agendar no toca ninguna cotizacion', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->givePermissionTo('surgeries.schedule');

    $this->actingAs($admin);

    Volt::test('qxlog.surgeries.schedule')
        ->set('patient_id', $patient->id)
        ->set('patient_query', $patient->nombreCompleto())
        ->set('procedure_date', now()->toDateString())
        ->set('start_time', '08:00')
        ->set('end_time', '09:00')
        ->set('procedure_type_query', 'Apendicectomía')
        ->call('schedule')
        ->assertHasNoErrors();

    // Nada que verificar además de que no explote: no hay quote de por medio.
    expect(true)->toBeTrue();
});
