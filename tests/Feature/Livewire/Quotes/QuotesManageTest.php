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

test('crea una cotizacion nueva en draft con renglones de honorarios', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $admin = manageAdmin($hospital);
    $this->actingAs($admin);

    Volt::test('qxlog.quotes.manage')
        ->set('patient_id', $patient->id)
        ->set('line_items', [
            ['surgical_role_id' => null, 'label' => 'Cirujano', 'amount' => 3500],
            ['surgical_role_id' => null, 'label' => 'Instrumentista', 'amount' => 800],
        ])
        ->set('hospital_cost', 7000)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $quote = SurgeryQuote::where('patient_id', $patient->id)->first();
    expect($quote)->not->toBeNull();
    expect($quote->status)->toBe('draft');
    expect((float) $quote->staff_fee)->toBe(4300.0);
    expect((float) $quote->total)->toBe(11300.0);
    expect($quote->lineItems)->toHaveCount(2);
});

test('guardar sin ningun renglon de honorarios falla validacion', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $admin = manageAdmin($hospital);
    $this->actingAs($admin);

    Volt::test('qxlog.quotes.manage')
        ->set('patient_id', $patient->id)
        ->set('line_items', [])
        ->set('hospital_cost', 500)
        ->call('save')
        ->assertHasErrors(['line_items']);
});

test('editar una cotizacion en draft actualiza la misma fila', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $admin = manageAdmin($hospital);
    $quote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id, 'hospital_cost' => 100,
    ]);
    $quote->syncLineItems([['surgical_role_id' => null, 'label' => 'Cirujano', 'amount' => 100]]);

    $this->actingAs($admin);

    Volt::test('qxlog.quotes.manage', ['quote' => $quote])
        ->assertSet('patient_id', $patient->id)
        ->set('line_items.0.amount', 500)
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $quote->fresh()->staff_fee)->toBe(500.0);
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

    $originalQuote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id, 'status' => 'draft', 'version' => 1, 'hospital_cost' => 5000,
    ]);
    $originalQuote->syncLineItems([['surgical_role_id' => null, 'label' => 'Cirujano', 'amount' => 5000]]);
    $originalQuote->markIssued();

    $this->actingAs($admin);

    Volt::test('qxlog.quotes.manage', ['quote' => $originalQuote])
        ->assertSet('patient_id', $patient->id)
        ->set('line_items.0.amount', 6000)
        ->set('hospital_cost', 6500)
        ->call('save')
        ->assertHasNoErrors();

    $newQuote = SurgeryQuote::where('patient_id', $patient->id)->where('status', 'draft')->latest('version')->first();

    expect($newQuote)->not->toBeNull();
    expect($newQuote->version)->toBe(2);
    expect((float) $newQuote->staff_fee)->toBe(6000.0);
    expect((float) $newQuote->hospital_cost)->toBe(6500.0);
    expect((float) $newQuote->total)->toBe(12500.0);

    $originalQuote->refresh();
    expect($originalQuote->status)->toBe('superseded');
    expect(SurgeryQuote::withoutGlobalScopes()->where('patient_id', $patient->id)->count())->toBe(2);
});

test('nueva version preserva surgical_case_id de la cotizacion original', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $admin = manageAdmin($hospital);

    $surgicalCase = SurgicalCase::factory()->for($hospital, 'hospital')->create(['patient_id' => $patient->id]);

    $originalQuote = SurgeryQuote::factory()->for($hospital, 'hospital')->create([
        'patient_id' => $patient->id, 'surgical_case_id' => $surgicalCase->id, 'status' => 'draft', 'version' => 1, 'hospital_cost' => 5000,
    ]);
    $originalQuote->syncLineItems([['surgical_role_id' => null, 'label' => 'Cirujano', 'amount' => 5000]]);
    $originalQuote->markIssued();

    $this->actingAs($admin);

    Volt::test('qxlog.quotes.manage', ['quote' => $originalQuote])
        ->set('line_items.0.amount', 7000)
        ->set('hospital_cost', 7500)
        ->call('save')
        ->assertHasNoErrors();

    $newQuote = SurgeryQuote::where('patient_id', $patient->id)->where('status', 'draft')->latest('version')->first();

    expect($newQuote->surgical_case_id)->toBe($surgicalCase->id);
    expect($newQuote->version)->toBe(2);
    $originalQuote->refresh();
    expect($originalQuote->surgical_case_id)->toBe($surgicalCase->id);
    expect($originalQuote->status)->toBe('superseded');
});

test('el paciente que no existe se guarda como texto libre', function () {
    $hospital = Hospital::factory()->create();
    $admin = manageAdmin($hospital);
    $this->actingAs($admin);

    Volt::test('qxlog.quotes.manage')
        ->set('patient_query', 'Elena Xitumul')
        ->call('useFreeTextPatient')
        ->assertSet('patient_id', null)
        ->assertSet('patient_free_text', 'Elena Xitumul');
});
