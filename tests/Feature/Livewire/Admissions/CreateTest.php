<?php

use App\Enums\AdmissionType;
use App\Models\Admission;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('admin can register a hospitalization admission selecting an existing patient', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->set('tipoAtencion', AdmissionType::HOSPITALIZACION->value)
        ->call('nextStep')
        ->call('selectPatient', $patient->id)
        ->call('nextStep')
        ->set('a_fecha_ingreso', now()->toDateString())
        ->set('a_medico_responsable', 'Dr. Test')
        ->call('save')
        ->assertHasNoErrors();

    $admission = Admission::first();
    expect($admission)->not->toBeNull();
    expect($admission->patient_id)->toBe($patient->id);
    expect($admission->hospital_id)->toBe($hospital->id);
    expect($admission->tipo_atencion)->toBe(AdmissionType::HOSPITALIZACION->value);
    expect($admission->qr_token)->not->toBeNull();
});

test('admin can register an admission creating a new patient inline', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->set('tipoAtencion', AdmissionType::HOSPITALIZACION->value)
        ->call('nextStep')
        ->set('p_primer_apellido', 'Gomez')
        ->set('p_primer_nombre', 'Ana')
        ->set('p_sexo', 'F')
        ->call('nextStep')
        ->set('a_fecha_ingreso', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $patient = Patient::first();
    expect($patient)->not->toBeNull();
    expect($patient->primer_apellido)->toBe('Gomez');
    expect($patient->primer_nombre)->toBe('Ana');

    $admission = Admission::first();
    expect($admission->patient_id)->toBe($patient->id);
});

test('emergency mode registers admission with minimal data', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->set('tipoAtencion', AdmissionType::EMERGENCIA->value)
        ->call('nextStep')
        ->set('p_primer_apellido', 'Perez')
        ->set('p_primer_nombre', 'Luis')
        ->call('nextStep')
        ->set('a_fecha_ingreso', now()->toDateString())
        ->set('a_impresion_clinica', 'Dolor torácico')
        ->call('save')
        ->assertHasNoErrors();

    $admission = Admission::first();
    expect($admission)->not->toBeNull();
    expect($admission->tipo_atencion)->toBe(AdmissionType::EMERGENCIA->value);
    expect($admission->patient->nombreCompleto())->toBe('Luis Perez');
    expect($admission->qr_token)->not->toBeNull();
});

test('admission requires attention type', function () {
    $user = User::factory()->create(['hospital_id' => Hospital::factory()->create()->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->call('nextStep')
        ->assertHasErrors(['tipoAtencion']);
});

test('admission requires patient names when creating inline', function () {
    $user = User::factory()->create(['hospital_id' => Hospital::factory()->create()->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->set('tipoAtencion', AdmissionType::HOSPITALIZACION->value)
        ->call('nextStep')
        ->call('nextStep')
        ->assertHasErrors(['p_primer_apellido', 'p_primer_nombre']);
});

test('admission requires episode date', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->set('tipoAtencion', AdmissionType::HOSPITALIZACION->value)
        ->call('nextStep')
        ->call('selectPatient', $patient->id)
        ->call('nextStep')
        ->set('a_fecha_ingreso', '')
        ->call('save')
        ->assertHasErrors(['a_fecha_ingreso']);
});
