<?php

use App\Models\AdmissionCustomFieldValue;
use App\Models\AdmissionType;
use App\Models\AdmissionTypeCustomField;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

it('renders and saves a custom field value for the selected admission type', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_custom_form']]);
    \App\Support\AdmissionTypeSeeder::seedDefaultsFor($hospital);
    $tipo = AdmissionType::where('hospital_id', $hospital->id)->where('slug', 'coex')->firstOrFail();
    $field = AdmissionTypeCustomField::create([
        'hospital_id' => $hospital->id,
        'admission_type_id' => $tipo->id,
        'step' => 1,
        'label' => 'Número de póliza',
        'slug' => 'numero_poliza',
        'field_type' => 'texto_corto',
        'required' => true,
        'sort_order' => 0,
        'active' => true,
    ]);
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');

    $component = Volt::actingAs($user)->test('admissions.create')
        ->set('admissionTypeId', $tipo->id)
        ->call('nextStep')
        ->assertSet('currentStep', 1)
        ->assertSeeText('Número de póliza')
        ->set("customFieldValues.{$field->id}", 'POL-12345')
        ->call('selectPatient', $patient->id)
        ->assertSet('currentStep', 2)
        ->call('nextStep')
        ->assertSet('currentStep', 3)
        ->call('nextStep')
        ->assertSet('currentStep', 4)
        ->set('a_fecha_ingreso', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect(AdmissionCustomFieldValue::where('custom_field_id', $field->id)->value('value'))
        ->toBe('POL-12345');
});

it('does not render or save custom fields when the hospital lacks the addon', function () {
    $hospital = Hospital::factory()->create(['addons' => []]);
    \App\Support\AdmissionTypeSeeder::seedDefaultsFor($hospital);
    $tipo = AdmissionType::where('hospital_id', $hospital->id)->where('slug', 'coex')->firstOrFail();
    $field = AdmissionTypeCustomField::create([
        'hospital_id' => $hospital->id,
        'admission_type_id' => $tipo->id,
        'step' => 1,
        'label' => 'Número de póliza',
        'slug' => 'numero_poliza',
        'field_type' => 'texto_corto',
        'required' => true,
        'sort_order' => 0,
        'active' => true,
    ]);
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');

    Volt::actingAs($user)->test('admissions.create')
        ->set('admissionTypeId', $tipo->id)
        ->call('nextStep')
        ->assertSet('currentStep', 1)
        ->assertDontSeeText('Número de póliza')
        ->call('selectPatient', $patient->id)
        ->assertSet('currentStep', 2)
        ->call('nextStep')
        ->assertSet('currentStep', 3)
        ->call('nextStep')
        ->assertSet('currentStep', 4)
        ->set('a_fecha_ingreso', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect(AdmissionCustomFieldValue::where('custom_field_id', $field->id)->exists())->toBeFalse();
});

it('does not render or save custom fields in rapid mode with no admission type selected', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_custom_form']]);
    \App\Support\AdmissionTypeSeeder::seedDefaultsFor($hospital);
    $urgencia = AdmissionType::where('hospital_id', $hospital->id)->where('slug', 'urgencia')->firstOrFail();
    $urgencia->update(['es_ingreso_rapido_default' => true]);
    $field = AdmissionTypeCustomField::create([
        'hospital_id' => $hospital->id,
        'admission_type_id' => null,
        'step' => 1,
        'label' => 'Número de póliza',
        'slug' => 'numero_poliza',
        'field_type' => 'texto_corto',
        'required' => false,
        'sort_order' => 0,
        'active' => true,
    ]);
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');

    Volt::actingAs($user)->test('admissions.create')
        ->set('isRapidMode', true)
        ->set('p_primer_apellido', 'Perez')
        ->set('p_primer_nombre', 'Luis')
        ->set('a_fecha_ingreso', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect(AdmissionCustomFieldValue::where('custom_field_id', $field->id)->exists())->toBeFalse();
});
