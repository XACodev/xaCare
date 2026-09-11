<?php
// tests/Feature/Models/SurgeryQuoteTest.php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\SurgeryQuote;

test('pertenece a su hospital, paciente y opcionalmente a una cirugia', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $creator = User::factory()->create(['hospital_id' => $hospital->id]);

    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id,
        'surgical_case_id' => null,
        'created_by_id' => $creator->id,
        'staff_fee' => 7000,
        'hospital_cost' => 7000,
    ]);

    expect($quote->hospital_id)->toBe($hospital->id);
    expect($quote->patient->is($patient))->toBeTrue();
    expect($quote->surgicalCase)->toBeNull();
    expect($quote->createdBy->is($creator))->toBeTrue();
});

test('total se calcula automaticamente al guardar', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();

    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id,
        'staff_fee' => 7000,
        'hospital_cost' => 7500.50,
    ]);

    expect((float) $quote->total)->toBe(14500.50);
});

test('slug es no adivinable y se usa como route key', function () {
    $quote = SurgeryQuote::factory()->create();

    expect($quote->slug)->toHaveLength(12);
    expect($quote->getRouteKeyName())->toBe('slug');
});

test('status por defecto es draft', function () {
    $quote = SurgeryQuote::factory()->create();

    expect($quote->status)->toBe('draft');
});
