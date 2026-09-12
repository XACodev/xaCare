<?php
// tests/Feature/Models/SurgeryQuoteLineItemTest.php

use App\Models\Hospital;
use App\Modules\QxLog\Models\SurgeryQuote;
use App\Modules\QxLog\Models\SurgicalRole;

test('syncLineItems reemplaza los renglones y recalcula total', function () {
    $hospital = Hospital::factory()->create();
    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create(['hospital_cost' => 1000]);
    $role = SurgicalRole::factory()->create(['hospital_id' => $hospital->id, 'name' => 'Cirujano']);

    $quote->syncLineItems([
        ['surgical_role_id' => $role->id, 'label' => 'Cirujano', 'amount' => 3500],
        ['surgical_role_id' => null, 'label' => 'Uso de quirófano', 'amount' => 1200],
    ]);

    $quote->refresh();
    expect($quote->lineItems)->toHaveCount(2);
    expect((float) $quote->staff_fee)->toBe(4700.0);
    expect((float) $quote->total)->toBe(5700.0);

    // Un segundo sync reemplaza, no acumula
    $quote->syncLineItems([
        ['surgical_role_id' => null, 'label' => 'Solo un renglón', 'amount' => 900],
    ]);
    $quote->refresh();
    expect($quote->lineItems)->toHaveCount(1);
    expect((float) $quote->staff_fee)->toBe(900.0);
    expect((float) $quote->total)->toBe(1900.0);
});

test('el renglon conserva su label aunque el rol se renombre despues', function () {
    $hospital = Hospital::factory()->create();
    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create(['hospital_cost' => 0]);
    $role = SurgicalRole::factory()->create(['hospital_id' => $hospital->id, 'name' => 'Instrumentista']);

    $quote->syncLineItems([
        ['surgical_role_id' => $role->id, 'label' => 'Instrumentista', 'amount' => 800],
    ]);

    $role->update(['name' => 'Instrumentista Senior']);

    expect($quote->lineItems()->first()->label)->toBe('Instrumentista');
});
