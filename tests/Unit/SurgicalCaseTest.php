<?php

use App\Models\SurgicalCase;

test('standardizes text fields to title case', function () {
    $case = new SurgicalCase;

    $case->patient_name = 'JUAN PEREZ';
    expect($case->patient_name)->toBe('Juan Perez');

    $case->procedure_type = 'APPENDECTOMY SURGERY';
    expect($case->procedure_type)->toBe('Appendectomy Surgery');
});

test('handles null text fields gracefully', function () {
    $case = new SurgicalCase;

    $case->patient_name = null;
    expect($case->patient_name)->toBeNull();

    $case->procedure_type = null;
    expect($case->procedure_type)->toBeNull();
});
