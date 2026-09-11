<?php
// tests/Feature/Livewire/Quotes/QuotesManageTest.php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\SurgeryQuote;
use App\Modules\QxLog\Models\SurgicalCase;
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

test('sin el permiso manage, editar una cotizacion existente del mismo hospital responde 403', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create(['patient_id' => $patient->id]);
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    Volt::test('qxlog.quotes.manage', ['quote' => $quote])->assertForbidden();
});

test('una cotizacion de otro hospital no puede editarse', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    $quoteB = SurgeryQuote::factory()->for($hospitalB, 'hospital')->create();
    $adminA = manageAdmin($hospitalA);

    $this->actingAs($adminA);

    Volt::test('qxlog.quotes.manage', ['quote' => $quoteB])->assertForbidden();
});

test('crear nueva version de una cotizacion emitida incrementa version y marca original como superseded', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $admin = manageAdmin($hospital);

    // Crear una cotización inicial en draft
    $originalQuote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id,
        'status' => 'draft',
        'version' => 1,
        'staff_fee' => 5000,
        'hospital_cost' => 5000,
    ]);

    // Marcarla como issued
    $originalQuote->markIssued();

    $this->actingAs($admin);

    // Montar el componente con la cotización emitida (simula el flujo "Nueva versión")
    Volt::test('qxlog.quotes.manage', ['quote' => $originalQuote])
        ->assertSet('patient_id', $patient->id)
        ->assertSet('staff_fee', 5000.0)
        ->assertSet('hospital_cost', 5000.0)
        ->set('staff_fee', 6000)
        ->set('hospital_cost', 6500)
        ->call('save')
        ->assertHasNoErrors();

    // Verificar que se creó una nueva versión
    $newQuote = SurgeryQuote::where('patient_id', $patient->id)
        ->where('status', 'draft')
        ->latest('version')
        ->first();

    expect($newQuote)->not->toBeNull();
    expect($newQuote->version)->toBe(2);
    expect((float) $newQuote->staff_fee)->toBe(6000.0);
    expect((float) $newQuote->hospital_cost)->toBe(6500.0);
    expect((float) $newQuote->total)->toBe(12500.0);

    // Verificar que la cotización original está marcada como superseded
    $originalQuote->refresh();
    expect($originalQuote->status)->toBe('superseded');

    // Verificar que hay exactamente 2 cotizaciones (no se eliminó la original)
    expect(SurgeryQuote::withoutGlobalScopes()->where('patient_id', $patient->id)->count())->toBe(2);
});

test('nueva version preserva surgical_case_id de la cotizacion original', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $admin = manageAdmin($hospital);

    // Crear un caso quirúrgico
    $surgicalCase = SurgicalCase::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id,
    ]);

    // Crear una cotización con surgical_case_id
    $originalQuote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id,
        'surgical_case_id' => $surgicalCase->id,
        'status' => 'draft',
        'version' => 1,
        'staff_fee' => 5000,
        'hospital_cost' => 5000,
    ]);

    // Marcarla como issued
    $originalQuote->markIssued();

    $this->actingAs($admin);

    // Montar el componente con la cotización emitida
    Volt::test('qxlog.quotes.manage', ['quote' => $originalQuote])
        ->set('staff_fee', 7000)
        ->set('hospital_cost', 7500)
        ->call('save')
        ->assertHasNoErrors();

    // Verificar que la nueva versión preservó el surgical_case_id
    $newQuote = SurgeryQuote::where('patient_id', $patient->id)
        ->where('status', 'draft')
        ->latest('version')
        ->first();

    expect($newQuote->surgical_case_id)->toBe($surgicalCase->id);
    expect($newQuote->version)->toBe(2);

    // Verificar que la original sigue teniendo el mismo surgical_case_id
    $originalQuote->refresh();
    expect($originalQuote->surgical_case_id)->toBe($surgicalCase->id);
    expect($originalQuote->status)->toBe('superseded');
});
