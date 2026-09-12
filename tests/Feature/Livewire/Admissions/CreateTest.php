<?php

use App\Enums\AdmissionType;
use App\Models\Admission;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
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
        ->call('nextStep')
        ->call('nextStep')
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
        ->call('nextStep')
        ->call('nextStep')
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
        ->call('nextStep')
        ->call('nextStep')
        ->call('setNow');

    expect($component->get('a_fecha_ingreso'))->toBe(now()->toDateString());
    expect($component->get('a_hora_ingreso'))->toBe(now()->format('H:i'));
});

test('new patient gets an automatic expediente number sequential by hospital', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    Patient::factory()->create(['hospital_id' => $hospital->id, 'expediente_no' => '9800']);
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->call('newPatient')
        ->set('p_primer_apellido', 'Gomez')
        ->set('p_primer_nombre', 'Ana')
        ->call('nextStep')
        ->call('nextStep')
        ->set('a_tipo_atencion', AdmissionType::HOSPITALIZACION->value)
        ->set('a_fecha_ingreso', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $patient = Patient::where('primer_nombre', 'Ana')->first();
    expect($patient->expediente_no)->toBe('9801');
});

test('selecting an existing patient allows editing their data before saving', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id, 'telefono' => '12345678']);
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->call('selectPatient', $patient->id)
        ->set('p_telefono', '87654321')
        ->call('nextStep')
        ->call('nextStep')
        ->set('a_tipo_atencion', AdmissionType::HOSPITALIZACION->value)
        ->set('a_fecha_ingreso', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect($patient->fresh()->telefono)->toBe('87654321');
});

test('nationality defaults to guatemalan unless foreign is selected', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->call('newPatient')
        ->set('p_primer_apellido', 'Lopez')
        ->set('p_primer_nombre', 'Maria')
        ->set('p_es_extranjero', false)
        ->call('nextStep')
        ->call('nextStep')
        ->set('a_tipo_atencion', AdmissionType::HOSPITALIZACION->value)
        ->set('a_fecha_ingreso', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $patient = Patient::where('primer_nombre', 'Maria')->first();
    expect($patient->nacionalidad)->toBe('Guatemalteco/a');
});

test('maternity fields and other hospitalizations are stored on admission', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->call('newPatient')
        ->set('p_primer_apellido', 'Ruiz')
        ->set('p_primer_nombre', 'Laura')
        ->set('p_sexo', 'F')
        ->call('nextStep')
        ->call('nextStep')
        ->set('a_tipo_atencion', AdmissionType::HOSPITALIZACION->value)
        ->set('a_fecha_ingreso', now()->toDateString())
        ->set('a_otras_hospitalizaciones', 'Apendicitis 2020')
        ->set('a_maternidad_no_hijo', '1')
        ->set('a_maternidad_sexo', 'M')
        ->call('save')
        ->assertHasNoErrors();

    $admission = Admission::first();
    expect($admission->otras_hospitalizaciones)->toBe('Apendicitis 2020');
    expect($admission->maternidad_no_hijo)->toBe('1');
    expect($admission->maternidad_sexo)->toBe('M');
});

test('sala y habitacion sugieren coincidencias del catalogo del hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    \App\Models\HospitalWard::factory()->for($hospital, 'hospital')->create(['name' => 'Sala 3']);
    \App\Models\HospitalWard::factory()->for($hospital, 'hospital')->create(['name' => 'Emergencia']);
    \App\Models\HospitalWard::factory()->for($otherHospital, 'hospital')->create(['name' => 'Sala 3']);

    \App\Models\HospitalRoom::factory()->for($hospital, 'hospital')->create(['name' => '101']);
    \App\Models\HospitalRoom::factory()->for($hospital, 'hospital')->create(['name' => '102']);

    $component = Volt::test('admissions.create')
        ->call('selectPatient', $patient->id)
        ->call('nextStep')
        ->call('nextStep')
        ->set('a_sala_ingreso', 'sala');

    expect($component->instance()->salaSuggestions)->toHaveCount(1);
    expect($component->instance()->salaSuggestions[0]['name'])->toBe('Sala 3');

    $component->set('a_habitacion', '10');
    expect($component->instance()->habitacionSuggestions)->toHaveCount(2);
});

test('foreign patient stores country document type and passport', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->call('newPatient')
        ->set('p_es_extranjero', true)
        ->set('p_id_country', 'SV')
        ->set('p_id_type', 'Pasaporte')
        ->set('p_dpi', 'A1234567')
        ->set('p_nacionalidad', 'Salvadoreño/a')
        ->set('p_pais_nacimiento', 'El Salvador')
        ->set('p_primer_apellido', 'Lopez')
        ->set('p_primer_nombre', 'Carlos')
        ->set('p_fecha_nacimiento', '1990-05-10')
        ->call('nextStep')
        ->call('nextStep')
        ->set('a_tipo_atencion', AdmissionType::HOSPITALIZACION->value)
        ->set('a_fecha_ingreso', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $patient = Patient::where('primer_nombre', 'Carlos')->first();
    expect($patient)->not->toBeNull();
    expect($patient->id_country)->toBe('SV');
    expect($patient->id_type)->toBe('Pasaporte');
    expect($patient->dpi)->toBe('A1234567');
    expect($patient->nacionalidad)->toBe('Salvadoreño/a');
    expect($patient->lugar_nacimiento)->toContain('El Salvador');
});

test('age is calculated in months and days for infants', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $this->actingAs($user);

    $birthDate = now()->subMonths(2)->subDays(5);

    $component = Volt::test('admissions.create')
        ->call('newPatient')
        ->set('p_primer_apellido', 'Perez')
        ->set('p_primer_nombre', 'Bebe')
        ->set('p_fecha_nacimiento', $birthDate->toDateString());

    $age = $component->instance()->edadCalculada;
    expect($age->years)->toBe(0);
    expect($age->months)->toBeGreaterThanOrEqual(2);
    expect($age->formatted())->toContain('meses');
});

test('multiple emergency contacts are stored as json', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->call('newPatient')
        ->set('p_primer_apellido', 'Garcia')
        ->set('p_primer_nombre', 'Maria')
        ->set('p_emergency_contacts', [
            ['nombre' => 'Juan Garcia', 'telefono' => '55551111', 'municipio' => 'Guatemala', 'departamento' => 'Guatemala'],
            ['nombre' => 'Ana Garcia', 'telefono' => '55552222', 'municipio' => 'Mixco', 'departamento' => 'Guatemala'],
        ])
        ->call('nextStep')
        ->call('nextStep')
        ->set('a_tipo_atencion', AdmissionType::HOSPITALIZACION->value)
        ->set('a_fecha_ingreso', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $patient = Patient::where('primer_nombre', 'Maria')->first();
    expect($patient->emergency_contacts)->toHaveCount(2);
    expect($patient->emergency_contacts[0]['nombre'])->toBe('Juan Garcia');
});

test('newborn patient can be registered without a name and linked to mother', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $mother = Patient::factory()->create(['hospital_id' => $hospital->id, 'sexo' => 'F', 'primer_nombre' => 'Mama']);
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->call('newPatient')
        ->set('p_es_recien_nacido', true)
        ->set('p_madre_paciente_id', $mother->id)
        ->set('p_sexo', 'M')
        ->set('p_fecha_nacimiento', now()->toDateString())
        ->call('nextStep')
        ->call('nextStep')
        ->set('a_tipo_atencion', AdmissionType::HOSPITALIZACION->value)
        ->set('a_fecha_ingreso', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $newborn = Patient::where('es_recien_nacido', true)->first();
    expect($newborn)->not->toBeNull();
    expect($newborn->madre_paciente_id)->toBe($mother->id);
});

test('guatemalan cui dpi validates 13 digits', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $this->actingAs($user);

    Volt::test('admissions.create')
        ->call('newPatient')
        ->set('p_primer_apellido', 'Test')
        ->set('p_primer_nombre', 'Corto')
        ->set('p_dpi', '123')
        ->call('nextStep')
        ->assertHasErrors(['p_dpi']);
});

test('medico responsable sugiere solo staff marcado como appears_as_suggestion del mismo hospital', function () {
    Permission::firstOrCreate(['name' => 'search.appear_as_suggestion', 'guard_name' => 'web']);

    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    $doctor = User::factory()->create(['hospital_id' => $hospital->id, 'name' => 'Dr. Fernando Lopez', 'role' => 'doctor']);

    // Rol 'admin' es global y no recibe 'search.appear_as_suggestion' por defecto
    // (a diferencia de los roles core doctor/instrumentist/circulating).
    User::factory()->create(['hospital_id' => $hospital->id, 'name' => 'Dr. Fernando NoSugerido', 'role' => 'admin']);

    $foreignDoctor = User::factory()->create(['hospital_id' => $otherHospital->id, 'name' => 'Dr. Fernando Otro', 'role' => 'doctor']);

    $component = Volt::test('admissions.create')
        ->call('selectPatient', $patient->id)
        ->call('nextStep')
        ->call('nextStep')
        ->set('a_medico_responsable', 'Fernando');

    expect($component->instance()->medicoSuggestions)->toHaveCount(1);
    expect($component->instance()->medicoSuggestions[0]['name'])->toBe('Dr. Fernando Lopez');
});
