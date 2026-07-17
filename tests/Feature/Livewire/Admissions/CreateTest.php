<?php

use App\Models\Admission;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Livewire\Volt\Volt;

test('nurse can register an admission selecting a patient', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->call('selectPatient', $patient->id)
        ->set('va_a_quirofano', true)
        ->set('fecha_ingreso', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $admission = Admission::first();
    expect($admission)->not->toBeNull();
    expect($admission->patient_id)->toBe($patient->id);
    expect($admission->hospital_id)->toBe($hospital->id);
    expect($admission->va_a_quirofano)->toBeTrue();
});

test('admission requires a patient', function () {
    $user = User::factory()->create(['hospital_id' => Hospital::factory()->create()->id]);
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->set('fecha_ingreso', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['patient_id']);
});
