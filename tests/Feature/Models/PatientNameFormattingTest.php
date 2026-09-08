<?php

use App\Models\Patient;

test('normalizes all four name fields to title case on save', function () {
    $patient = Patient::factory()->make([
        'primer_apellido' => 'DE LA cruz',
        'segundo_apellido' => 'VALDEZ',
        'primer_nombre' => 'JUan',
        'segundo_nombre' => 'carLOS',
    ]);

    expect($patient->primer_apellido)->toBe('De la Cruz');
    expect($patient->segundo_apellido)->toBe('Valdez');
    expect($patient->primer_nombre)->toBe('Juan');
    expect($patient->segundo_nombre)->toBe('Carlos');
});
