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

test('admin can register an admission selecting an existing patient', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->call('selectPatient', $patient->id)
        ->set('a_tipo_atencion', AdmissionType::HOSPITALIZACION->value)
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
    expect($admission->completo)->toBeTrue();
});

test('admin can register an admission creating a new patient inline', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->call('newPatient')
        ->set('p_primer_apellido', 'Gomez')
        ->set('p_primer_nombre', 'Ana')
        ->set('p_sexo', 'F')
        ->call('nextStep')
        ->call('nextStep')
        ->set('a_tipo_atencion', AdmissionType::HOSPITALIZACION->value)
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

test('rapid mode registers urgent admission with minimal data and marks incomplete', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->set('isRapidMode', true)
        ->set('p_primer_apellido', 'Perez')
        ->set('p_primer_nombre', 'Luis')
        ->set('a_fecha_ingreso', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $admission = Admission::first();
    expect($admission)->not->toBeNull();
    expect($admission->tipo_atencion)->toBe(AdmissionType::URGENCIA->value);
    expect($admission->completo)->toBeFalse();
    expect($admission->patient->nombreCompleto())->toBe('Luis Perez');
    expect($admission->qr_token)->not->toBeNull();
});

test('admission requires personal data when creating inline', function () {
    $user = User::factory()->create(['hospital_id' => Hospital::factory()->create()->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->call('newPatient')
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
        ->call('selectPatient', $patient->id)
        ->set('a_fecha_ingreso', '')
        ->call('save')
        ->assertHasErrors(['a_fecha_ingreso']);
});

test('set now button fills current date and time', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    $component = Volt::test('admissions.create')
        ->call('selectPatient', $patient->id)
        ->call('setNow');

    expect($component->get('a_fecha_ingreso'))->toBe(now()->toDateString());
    expect($component->get('a_hora_ingreso'))->toBe(now()->format('H:i'));
});
