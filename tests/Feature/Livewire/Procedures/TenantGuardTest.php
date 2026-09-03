<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\SurgicalRole;
use App\Models\User;
use Livewire\Volt\Volt;

test('procedures.create aborts when instrumentist has no hospital_id', function () {
    $user = User::withoutEvents(fn () => User::factory()->create([
        'role' => 'instrumentist',
        'hospital_id' => null,
    ]));

    $this->actingAs($user);

    Volt::test('procedures.create')
        ->assertStatus(422);
});

test('procedures.create rejects a patient from another hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $user = User::factory()->create(['role' => 'instrumentist', 'hospital_id' => $hospital->id]);
    SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Instrumentista', 'slug' => 'instrumentista']);
    $patient = Patient::factory()->create(['hospital_id' => $otherHospital->id]);

    $this->actingAs($user);

    Volt::test('procedures.create')
        ->set('patient_id', $patient->id)
        ->set('procedure_type', 'Apendicectomia')
        ->set('start_time', '08:00')
        ->set('end_time', '09:00')
        ->call('save')
        ->assertHasErrors(['patient_id']);
});

test('procedures.create rejects a surgical role from another hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $user = User::factory()->create(['role' => 'instrumentist', 'hospital_id' => $hospital->id]);
    SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Instrumentista', 'slug' => 'instrumentista']);
    $otherRole = SurgicalRole::factory()->for($otherHospital, 'hospital')->create(['name' => 'Circulante', 'slug' => 'circulante']);

    $this->actingAs($user);

    Volt::test('procedures.create')
        ->set('procedure_type', 'Apendicectomia')
        ->set('start_time', '08:00')
        ->set('end_time', '09:00')
        ->set('assignments.0.role_id', $otherRole->id)
        ->call('save')
        ->assertHasErrors(['assignments.0.role_id']);
});

test('procedures.create rejects an assigned user from another hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $user = User::factory()->create(['role' => 'instrumentist', 'hospital_id' => $hospital->id]);
    SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Instrumentista', 'slug' => 'instrumentista']);
    $otherUser = User::factory()->create(['hospital_id' => $otherHospital->id]);

    $this->actingAs($user);

    Volt::test('procedures.create')
        ->set('procedure_type', 'Apendicectomia')
        ->set('start_time', '08:00')
        ->set('end_time', '09:00')
        ->set('assignments.0.user_id', $otherUser->id)
        ->call('save')
        ->assertHasErrors(['assignments.0.user_id']);
});

test('user suggestions only include users from the same hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $user = User::factory()->create(['role' => 'instrumentist', 'hospital_id' => $hospital->id, 'name' => 'Local User']);
    User::factory()->create(['hospital_id' => $otherHospital->id, 'name' => 'Remote User']);
    SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Instrumentista', 'slug' => 'instrumentista']);

    $this->actingAs($user);

    $component = Volt::test('procedures.create');
    $suggestions = ($component->instance()->userSuggestions)('User');

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['name'])->toBe('Local User');
});
