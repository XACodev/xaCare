<?php

use App\Models\Admission;
use App\Models\Patient;

test('admission belongs to a patient and casts flags', function () {
    $patient = Patient::factory()->create();
    $admission = Admission::factory()->create([
        'patient_id' => $patient->id,
        'hospital_id' => $patient->hospital_id,
        'va_a_quirofano' => 1,
    ]);

    expect($admission->patient->is($patient))->toBeTrue();
    expect($admission->va_a_quirofano)->toBeTrue();
});
