<?php

use App\Models\Admission;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\User;
use Livewire\Volt\Volt;

test('instrumentist selects a patient and it is stored on the procedure', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'instrumentist', 'use_pay_scheme' => false]);
    $patient = Patient::factory()->create([
        'hospital_id' => $hospital->id,
        'primer_nombre' => 'Ana', 'segundo_nombre' => null,
        'primer_apellido' => 'Gomez', 'segundo_apellido' => null,
    ]);
    Admission::factory()->create(['hospital_id' => $hospital->id, 'patient_id' => $patient->id, 'va_a_quirofano' => true]);
    $this->actingAs($user);

    Volt::test('procedures.create')
        ->call('selectPatient', $patient->id)
        ->set('procedure_type', 'Apendicectomia')
        ->set('start_time', '08:00')
        ->set('end_time', '09:00')
        ->set('doctor_query', 'Dr House')
        ->set('circulating_query', 'Enf Rivas')
        ->call('save')
        ->assertHasNoErrors();

    $procedure = Procedure::withoutGlobalScopes()->first();
    expect($procedure->patient_id)->toBe($patient->id);
    expect($procedure->patient_name)->toBe('Ana Gomez');
});
