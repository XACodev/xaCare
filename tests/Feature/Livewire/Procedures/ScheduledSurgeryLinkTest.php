<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\SurgeryStatus;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\SurgicalRole;
use Livewire\Volt\Volt;

function makeInstrumentistWithHospital(): array
{
    $hospital = Hospital::factory()->create();
    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Instrumentista', 'slug' => 'instrumentista']);
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'instrumentist', 'use_pay_scheme' => false]);

    return [$hospital, $role, $user];
}

test('detecta una cirugia programada del paciente seleccionado', function () {
    [$hospital, $role, $user] = makeInstrumentistWithHospital();
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $scheduled = SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'is_draft' => false,
        'procedure_type' => 'Apendicectomia',
    ]);

    $this->actingAs($user);

    $component = Volt::test('qxlog.procedures.create')->call('selectPatient', $patient->id);

    expect($component->instance()->matching_surgery?->id)->toBe($scheduled->id);
});

test('no detecta borradores ni cirugias de otro paciente', function () {
    [$hospital, $role, $user] = makeInstrumentistWithHospital();
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $otherPatient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'patient_id' => $patient->id, 'is_draft' => true]);
    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'patient_id' => $otherPatient->id, 'is_draft' => false]);

    $this->actingAs($user);

    $component = Volt::test('qxlog.procedures.create')->call('selectPatient', $patient->id);

    expect($component->instance()->matching_surgery)->toBeNull();
});

test('usar la cirugia sugerida precarga tipo de procedimiento y staff tentativo', function () {
    [$hospital, $role, $user] = makeInstrumentistWithHospital();
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $scheduled = SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'is_draft' => false,
        'procedure_type' => 'Colecistectomia',
    ]);
    SurgicalAssignment::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_case_id' => $scheduled->id,
        'surgical_role_id' => $role->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user);

    $component = Volt::test('qxlog.procedures.create')
        ->call('selectPatient', $patient->id)
        ->call('useScheduledSurgery');

    expect($component->get('procedure_type'))->toBe('Colecistectomia')
        ->and($component->get('assignments'))->toHaveCount(1)
        ->and($component->get('assignments')[0]['role_id'])->toBe($role->id);
});

test('guardar tras usar la sugerencia completa la cirugia programada sin duplicar', function () {
    [$hospital, $role, $user] = makeInstrumentistWithHospital();
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $completedStatus = SurgeryStatus::factory()->create(['hospital_id' => $hospital->id, 'is_completed' => true]);
    $scheduled = SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'is_draft' => false,
        'procedure_type' => 'Colecistectomia',
    ]);

    $this->actingAs($user);

    Volt::test('qxlog.procedures.create')
        ->call('selectPatient', $patient->id)
        ->call('useScheduledSurgery')
        ->set('start_time', '08:00')
        ->set('end_time', '09:00')
        ->set('assignments.0.role_id', $role->id)
        ->set('assignments.0.user_id', $user->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(SurgicalCase::withoutGlobalScopes()->count())->toBe(1);

    $scheduled->refresh();
    expect($scheduled->is_draft)->toBeFalse()
        ->and($scheduled->surgery_status_id)->toBe($completedStatus->id)
        ->and($scheduled->assignments)->toHaveCount(1);
});

test('sin usar la sugerencia, guardar sigue creando una cirugia nueva (sin regresion)', function () {
    [$hospital, $role, $user] = makeInstrumentistWithHospital();
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'is_draft' => false,
    ]);

    $this->actingAs($user);

    Volt::test('qxlog.procedures.create')
        ->call('selectPatient', $patient->id)
        ->set('procedure_type', 'Apendicectomia')
        ->set('start_time', '08:00')
        ->set('end_time', '09:00')
        ->set('assignments.0.role_id', $role->id)
        ->set('assignments.0.user_id', $user->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(SurgicalCase::withoutGlobalScopes()->count())->toBe(2);
});
