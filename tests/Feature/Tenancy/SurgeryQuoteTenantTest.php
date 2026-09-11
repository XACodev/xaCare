<?php
// tests/Feature/Tenancy/SurgeryQuoteTenantTest.php

use App\Models\Hospital;
use App\Models\Patient;
use App\Modules\QxLog\Models\SurgeryQuote;

test('una cotizacion queda scoped al hospital del usuario autenticado', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $admin = \App\Models\User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $this->actingAs($admin);

    $quote = SurgeryQuote::create([
        'patient_id' => $patient->id,
        'staff_fee' => 100,
        'hospital_cost' => 200,
        'created_by_id' => $admin->id,
    ]);

    expect($quote->hospital_id)->toBe($hospital->id);
});

test('las cotizaciones de otro hospital no son visibles', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    $quoteB = SurgeryQuote::factory()->for($hospitalB, 'hospital')->create();

    $adminA = \App\Models\User::factory()->create(['hospital_id' => $hospitalA->id, 'role' => 'admin']);
    $this->actingAs($adminA);

    expect(SurgeryQuote::find($quoteB->id))->toBeNull();
});
