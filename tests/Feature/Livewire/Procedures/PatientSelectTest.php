<?php

use App\Models\Admission;
use App\Models\Hospital;
use App\Models\Patient;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\SurgicalRole;
use App\Models\User;
use Livewire\Volt\Volt;

test('instrumentist selects a patient and it is stored on the procedure', function () {
    $hospital = Hospital::factory()->create();
    SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Instrumentista', 'slug' => 'instrumentista']);
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
        ->call('save')
        ->assertHasNoErrors();

    $surgicalCase = SurgicalCase::withoutGlobalScopes()->first();
    expect($surgicalCase->patient_id)->toBe($patient->id);
    expect($surgicalCase->patient_name)->toBe('Ana Gomez');
});

test('instrumentist can register a procedure with a free-text patient name for emergency cases', function () {
    $hospital = Hospital::factory()->create();
    SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Instrumentista', 'slug' => 'instrumentista']);
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'instrumentist', 'use_pay_scheme' => false]);
    $this->actingAs($user);

    Volt::test('procedures.create')
        ->set('patient_query', 'Paciente de Emergencia')
        ->set('procedure_type', 'Cesarea')
        ->set('start_time', '02:00')
        ->set('end_time', '03:00')
        ->call('save')
        ->assertHasNoErrors();

    $surgicalCase = SurgicalCase::withoutGlobalScopes()->first();
    expect($surgicalCase->patient_id)->toBeNull();
    expect($surgicalCase->patient_name)->toBe('Paciente De Emergencia'); // Title Case cast
});

test('registering a procedure requires a patient, selected or typed', function () {
    $hospital = Hospital::factory()->create();
    SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Instrumentista', 'slug' => 'instrumentista']);
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'instrumentist']);
    $this->actingAs($user);

    Volt::test('procedures.create')
        ->set('procedure_type', 'Cesarea')
        ->set('start_time', '02:00')
        ->set('end_time', '03:00')
        ->call('save')
        ->assertHasErrors(['patient_query']);
});
