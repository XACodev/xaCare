<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\SurgeryQuote;
use App\Modules\QxLog\Models\SurgicalCase;

function makeQuoteContext(): array
{
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $creator = User::factory()->create(['hospital_id' => $hospital->id]);

    return [$hospital, $patient, $creator];
}

test('crear la primera cotizacion de un paciente queda en version 1, draft', function () {
    [$hospital, $patient, $creator] = makeQuoteContext();

    $quote = SurgeryQuote::saveDraftOrNewVersion([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'staff_fee' => 7000,
        'hospital_cost' => 7000,
        'hospital_cost_note' => 'Incluye material de osteosíntesis.',
        'created_by_id' => $creator->id,
    ]);

    expect($quote->version)->toBe(1);
    expect($quote->status)->toBe('draft');
});

test('editar una cotizacion en draft actualiza la misma fila, sin nueva version', function () {
    [$hospital, $patient, $creator] = makeQuoteContext();

    $quote = SurgeryQuote::saveDraftOrNewVersion([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'staff_fee' => 7000,
        'hospital_cost' => 7000,
        'created_by_id' => $creator->id,
    ]);

    $edited = SurgeryQuote::saveDraftOrNewVersion([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'staff_fee' => 7500,
        'hospital_cost' => 7000,
        'created_by_id' => $creator->id,
    ]);

    expect($edited->id)->toBe($quote->id);
    expect($edited->version)->toBe(1);
    expect((float) $edited->staff_fee)->toBe(7500.0);
    expect(SurgeryQuote::withoutGlobalScopes()->where('patient_id', $patient->id)->count())->toBe(1);
});

test('editar una cotizacion ya emitida crea una nueva version y supersede la anterior', function () {
    [$hospital, $patient, $creator] = makeQuoteContext();

    $quote = SurgeryQuote::saveDraftOrNewVersion([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'staff_fee' => 7000,
        'hospital_cost' => 7000,
        'created_by_id' => $creator->id,
    ]);
    $quote->markIssued();

    $revised = SurgeryQuote::saveDraftOrNewVersion([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'staff_fee' => 8000,
        'hospital_cost' => 7000,
        'created_by_id' => $creator->id,
    ]);

    expect($revised->id)->not->toBe($quote->id);
    expect($revised->version)->toBe(2);
    expect($revised->status)->toBe('draft');
    expect($quote->fresh()->status)->toBe('superseded');
});

test('markIssued cambia el status sin tocar la version', function () {
    [$hospital, $patient, $creator] = makeQuoteContext();

    $quote = SurgeryQuote::saveDraftOrNewVersion([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'staff_fee' => 7000,
        'hospital_cost' => 7000,
        'created_by_id' => $creator->id,
    ]);

    $quote->markIssued();

    expect($quote->fresh()->status)->toBe('issued');
    expect($quote->fresh()->version)->toBe(1);
});

test('attachToSurgicalCase vincula sin crear nueva version ni cambiar status', function () {
    [$hospital, $patient, $creator] = makeQuoteContext();

    $quote = SurgeryQuote::saveDraftOrNewVersion([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'staff_fee' => 7000,
        'hospital_cost' => 7000,
        'created_by_id' => $creator->id,
    ]);
    $quote->markIssued();

    $case = SurgicalCase::factory()->for($hospital, 'hospital')->create(['patient_id' => $patient->id]);
    $quote->attachToSurgicalCase($case);

    expect($quote->fresh()->surgical_case_id)->toBe($case->id);
    expect($quote->fresh()->status)->toBe('issued');
    expect($quote->fresh()->version)->toBe(1);
});

test('latestFor devuelve la version mas reciente no superseded', function () {
    [$hospital, $patient, $creator] = makeQuoteContext();

    $first = SurgeryQuote::saveDraftOrNewVersion([
        'hospital_id' => $hospital->id, 'patient_id' => $patient->id,
        'staff_fee' => 1, 'hospital_cost' => 1, 'created_by_id' => $creator->id,
    ]);
    $first->markIssued();

    $second = SurgeryQuote::saveDraftOrNewVersion([
        'hospital_id' => $hospital->id, 'patient_id' => $patient->id,
        'staff_fee' => 2, 'hospital_cost' => 2, 'created_by_id' => $creator->id,
    ]);

    $latest = SurgeryQuote::latestFor($hospital->id, $patient->id);

    expect($latest->id)->toBe($second->id);
});
