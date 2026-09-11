<?php
// tests/Feature/Livewire/Quotes/QuotesManageTest.php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\SurgeryQuote;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'surgeries.budget.manage', 'guard_name' => 'web']);
});

function manageAdmin(Hospital $hospital): User
{
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->givePermissionTo('surgeries.budget.manage');

    return $admin;
}

test('crea una cotizacion nueva en draft', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $admin = manageAdmin($hospital);
    $this->actingAs($admin);

    Volt::test('qxlog.quotes.manage')
        ->set('patient_id', $patient->id)
        ->set('staff_fee', 7000)
        ->set('hospital_cost', 7000)
        ->set('hospital_cost_note', 'Incluye material.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $quote = SurgeryQuote::where('patient_id', $patient->id)->first();
    expect($quote)->not->toBeNull();
    expect($quote->status)->toBe('draft');
    expect((float) $quote->total)->toBe(14000.0);
});

test('editar una cotizacion en draft actualiza la misma fila', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $admin = manageAdmin($hospital);
    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id, 'staff_fee' => 100, 'hospital_cost' => 100,
    ]);

    $this->actingAs($admin);

    Volt::test('qxlog.quotes.manage', ['quote' => $quote])
        ->assertSet('patient_id', $patient->id)
        ->set('staff_fee', 500)
        ->call('save')
        ->assertHasNoErrors();

    expect($quote->fresh()->staff_fee)->toEqual(500);
    expect(SurgeryQuote::withoutGlobalScopes()->where('patient_id', $patient->id)->count())->toBe(1);
});

test('sin el permiso manage, el componente responde 403', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    Volt::test('qxlog.quotes.manage')->assertForbidden();
});

test('una cotizacion de otro hospital no puede editarse', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    $quoteB = SurgeryQuote::factory()->for($hospitalB, 'hospital')->create();
    $adminA = manageAdmin($hospitalA);

    $this->actingAs($adminA);

    Volt::test('qxlog.quotes.manage', ['quote' => $quoteB])->assertForbidden();
});
