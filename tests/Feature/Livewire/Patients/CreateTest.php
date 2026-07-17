<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Livewire\Volt\Volt;

test('authenticated user can create a patient scoped to their hospital', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $this->actingAs($user);

    Volt::test('patients.create')
        ->set('primer_apellido', 'gomez')
        ->set('primer_nombre', 'ana')
        ->set('sexo', 'F')
        ->call('save')
        ->assertHasNoErrors();

    $patient = Patient::first();
    expect($patient)->not->toBeNull();
    expect($patient->hospital_id)->toBe($hospital->id);
    expect($patient->primer_apellido)->toBe('Gomez'); // Title Case cast
});

test('creating a patient requires names', function () {
    $user = User::factory()->create(['hospital_id' => Hospital::factory()->create()->id]);
    $this->actingAs($user);

    Volt::test('patients.create')
        ->set('primer_apellido', '')
        ->set('primer_nombre', '')
        ->call('save')
        ->assertHasErrors(['primer_apellido', 'primer_nombre']);
});
