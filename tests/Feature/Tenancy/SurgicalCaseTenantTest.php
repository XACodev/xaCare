<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\SurgicalCase;
use App\Models\User;

test('procedures are scoped by hospital and link to a patient', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'instrumentist']);
    $this->actingAs($user);

    $procedure = SurgicalCase::create([
        'procedure_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '09:00',
        'patient_name' => 'Ana Gomez',
        'patient_id' => $patient->id,
        'procedure_type' => 'Apendicectomia',
        'status' => 'pending',
    ]);

    expect($procedure->hospital_id)->toBe($hospital->id);
    expect($procedure->patient->is($patient))->toBeTrue();
});
