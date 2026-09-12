<?php
// tests/Feature/Livewire/Quotes/QuotesVerifyTest.php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\SurgeryQuote;
use Livewire\Volt\Volt;

test('una cotizacion vigente del propio hospital verifica sin exponer montos', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $creator = User::factory()->create(['hospital_id' => $hospital->id]);
    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id, 'created_by_id' => $creator->id, 'staff_fee' => 9999,
    ]);
    $viewer = User::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($viewer);

    $component = Volt::test('qxlog.quotes.verify', ['quote' => $quote]);
    $component->assertSee($quote->slug);
    $component->assertDontSee('9999');
});

test('una cotizacion eliminada no verifica', function () {
    $hospital = Hospital::factory()->create();
    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create();
    $quote->delete();
    $viewer = User::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($viewer);

    $this->get(route('quotes.verify', $quote->slug))->assertNotFound();
});

test('la ruta de verificacion requiere autenticacion', function () {
    $hospital = Hospital::factory()->create();
    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create();

    $this->get(route('quotes.verify', $quote))->assertRedirect(route('login'));
});
