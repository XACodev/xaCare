<?php
// tests/Feature/Livewire/Quotes/QuotesPrintTest.php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\SurgeryQuote;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'surgeries.budget.view_total', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'surgeries.budget.view_own', 'guard_name' => 'web']);
});

test('la vista imprimible muestra el total y la nota, sin desglose de honorario/costo', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->givePermissionTo('surgeries.budget.view_total');

    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id, 'staff_fee' => 7000, 'hospital_cost' => 7500,
        'hospital_cost_note' => 'Incluye material de osteosíntesis.',
    ]);

    $this->actingAs($admin);

    Volt::test('qxlog.quotes.print', ['quote' => $quote])
        ->assertSee(number_format(14500, 2))
        ->assertSee('Incluye material de osteosíntesis.')
        ->assertDontSee(number_format(7000, 2))
        ->assertDontSee(number_format(7500, 2));
});

test('view_own no puede imprimir', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $staff = User::factory()->create(['hospital_id' => $hospital->id]);
    $staff->givePermissionTo('surgeries.budget.view_own');

    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create(['patient_id' => $patient->id]);

    $this->actingAs($staff);

    Volt::test('qxlog.quotes.print', ['quote' => $quote])->assertForbidden();
});
