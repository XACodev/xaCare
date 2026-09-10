<?php

use App\Modules\QxLog\Models\SurgicalCase;

// El accessor Attribute de título-case para `procedure_type` (columna string libre) fue
// eliminado en Task 3 del plan de catálogos qxlog al reemplazar la columna por
// `procedure_type_id` (FK a ProcedureType), así que este test ya no cubre ese caso.
// El normalizado de nombre ahora vive en el mutator `name` de ProcedureType (ver
// tests/Feature/Models/ProcedureTypeTest.php), consistente con Patient/User.
test('standardizes text fields to title case', function () {
    $case = new SurgicalCase;

    $case->patient_name = 'JUAN PEREZ';
    expect($case->patient_name)->toBe('Juan Perez');
});

test('handles null text fields gracefully', function () {
    $case = new SurgicalCase;

    $case->patient_name = null;
    expect($case->patient_name)->toBeNull();
});
