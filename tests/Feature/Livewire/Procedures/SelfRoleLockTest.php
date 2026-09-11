<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalRole;
use App\Models\User;
use Livewire\Volt\Volt;

test('an instrumentist cannot reassign their own row to a different role to change their pay', function () {
    $hospital = Hospital::factory()->create();
    $instrumentist = User::factory()->create(['role' => 'instrumentist', 'hospital_id' => $hospital->id]);

    $instrumentistRole = SurgicalRole::where('hospital_id', $hospital->id)->where('name', 'Instrumentista')->first();
    $circulanteRole = SurgicalRole::where('hospital_id', $hospital->id)->where('name', 'Circulante')->first();

    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);

    $this->actingAs($instrumentist);

    $component = Volt::test('qxlog.procedures.create')
        ->call('selectPatient', $patient->id)
        ->set('procedure_type_query', 'Apendicectomia')
        // El usuario manipula su propia fila (index 0, user_id = el mismo) hacia un rol
        // distinto al que el sistema le auto-asignó al montar el componente.
        ->set('assignments.0.role_id', $circulanteRole->id)
        ->call('save')
        ->assertHasNoErrors();

    $assignment = SurgicalAssignment::where('user_id', $instrumentist->id)->first();

    expect($assignment)->not->toBeNull();
    expect($assignment->surgical_role_id)->toBe($instrumentistRole->id);
});

test('an admin can freely assign any role to any person, including themselves', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);

    $role = SurgicalRole::where('hospital_id', $hospital->id)->where('name', 'Circulante')->first();

    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);

    $this->actingAs($admin);

    Volt::test('qxlog.procedures.create')
        ->call('selectPatient', $patient->id)
        ->set('procedure_type_query', 'Apendicectomia')
        ->set('assignments.0.role_id', $role->id)
        ->call('save')
        ->assertHasNoErrors();

    $assignment = SurgicalAssignment::where('user_id', $admin->id)->first();

    expect($assignment)->not->toBeNull();
    expect($assignment->surgical_role_id)->toBe($role->id);
});
