<?php

use App\Models\AdmissionType;
use App\Models\AdmissionTypeCustomField;
use App\Models\Hospital;
use App\Models\User;
use App\Support\AdmissionFormSection;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'settings.manage', 'guard_name' => 'web']);
});

function admissionTypesEditAdmin(Hospital $hospital): User
{
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);
    $admin->givePermissionTo('settings.manage');

    return $admin;
}

test('togglea visibilidad y obligatoriedad de secciones', function () {
    $hospital = Hospital::factory()->create();
    $admin = admissionTypesEditAdmin($hospital);
    $this->actingAs($admin);

    $tipo = AdmissionType::factory()->for($hospital)->create([
        'visible_sections' => ['seguro'],
        'required_sections' => ['seguro'],
    ]);

    Volt::test('settings.admission-types-edit', ['admissionType' => $tipo])
        ->assertSee(AdmissionFormSection::Seguro->label())
        ->call('toggleSection', AdmissionFormSection::ContactosEmergencia->value, true)
        ->call('toggleRequired', AdmissionFormSection::ContactosEmergencia->value, true)
        ->call('toggleSection', AdmissionFormSection::Seguro->value, false);

    $tipo->refresh();

    expect($tipo->visible_sections)->toBe([AdmissionFormSection::ContactosEmergencia->value])
        ->and($tipo->required_sections)->toBe([AdmissionFormSection::ContactosEmergencia->value]);
});

test('toggleSection aborta con una clave de seccion invalida', function () {
    $hospital = Hospital::factory()->create();
    $admin = admissionTypesEditAdmin($hospital);
    $this->actingAs($admin);

    $tipo = AdmissionType::factory()->for($hospital)->create();

    Volt::test('settings.admission-types-edit', ['admissionType' => $tipo])
        ->call('toggleSection', 'seccion_inventada', true)
        ->assertStatus(422);
});

test('toggleRequired aborta al marcar obligatoria una seccion que no esta visible', function () {
    $hospital = Hospital::factory()->create();
    $admin = admissionTypesEditAdmin($hospital);
    $this->actingAs($admin);

    $tipo = AdmissionType::factory()->for($hospital)->create([
        'visible_sections' => [],
        'required_sections' => [],
    ]);

    Volt::test('settings.admission-types-edit', ['admissionType' => $tipo])
        ->call('toggleRequired', AdmissionFormSection::Seguro->value, true)
        ->assertStatus(422);
});

test('toggleRequired aborta con una clave de seccion invalida', function () {
    $hospital = Hospital::factory()->create();
    $admin = admissionTypesEditAdmin($hospital);
    $this->actingAs($admin);

    $tipo = AdmissionType::factory()->for($hospital)->create([
        'visible_sections' => ['seccion_inventada'],
    ]);

    Volt::test('settings.admission-types-edit', ['admissionType' => $tipo])
        ->call('toggleRequired', 'seccion_inventada', true)
        ->assertStatus(422);
});

test('desactiva un campo personalizado del tipo sin borrarlo', function () {
    $hospital = Hospital::factory()->create();
    $admin = admissionTypesEditAdmin($hospital);
    $this->actingAs($admin);

    $tipo = AdmissionType::factory()->for($hospital)->create();
    $field = AdmissionTypeCustomField::factory()->for($hospital)->for($tipo)->create([
        'label' => 'Alergias conocidas',
    ]);

    Volt::test('settings.admission-types-edit', ['admissionType' => $tipo])
        ->assertSee('Alergias conocidas')
        ->call('deleteCustomField', $field->id)
        ->assertDontSee('Alergias conocidas');

    $field->refresh();
    expect($field->exists)->toBeTrue()
        ->and($field->active)->toBeFalse();
});

test('las respuestas historicas de un campo desactivado sobreviven y el campo deja de aparecer en el wizard', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_custom_form']]);
    \App\Support\AdmissionTypeSeeder::seedDefaultsFor($hospital);
    $tipo = AdmissionType::where('hospital_id', $hospital->id)->where('slug', 'coex')->firstOrFail();
    $admin = admissionTypesEditAdmin($hospital);
    $this->actingAs($admin);

    $field = AdmissionTypeCustomField::create([
        'hospital_id' => $hospital->id,
        'admission_type_id' => $tipo->id,
        'step' => 1,
        'label' => 'Alergias conocidas',
        'slug' => 'alergias_conocidas',
        'field_type' => 'texto_corto',
        'required' => false,
        'sort_order' => 0,
        'active' => true,
    ]);

    $admission = \App\Models\Admission::query()->create([
        'hospital_id' => $hospital->id,
        'patient_id' => \App\Models\Patient::factory()->for($hospital)->create()->id,
        'fecha_ingreso' => now()->toDateString(),
        'completo' => true,
        'va_a_quirofano' => false,
    ]);

    $value = \App\Models\AdmissionCustomFieldValue::create([
        'admission_id' => $admission->id,
        'custom_field_id' => $field->id,
        'value' => 'Penicilina',
    ]);

    Volt::test('settings.admission-types-edit', ['admissionType' => $tipo])
        ->call('deleteCustomField', $field->id);

    expect(\App\Models\AdmissionCustomFieldValue::find($value->id))->not->toBeNull();
    expect(\App\Models\AdmissionCustomFieldValue::find($value->id)->value)->toBe('Penicilina');

    $wizardUser = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $wizardUser->assignRole('admin');

    \Livewire\Volt\Volt::actingAs($wizardUser)->test('admissions.create')
        ->set('admissionTypeId', $tipo->id)
        ->call('nextStep')
        ->assertDontSeeText('Alergias conocidas');
});

test('no elimina un campo personalizado de otro hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = admissionTypesEditAdmin($hospital);
    $this->actingAs($admin);

    $tipo = AdmissionType::factory()->for($hospital)->create();
    $foreignField = AdmissionTypeCustomField::factory()->for($otherHospital)->create();

    Volt::test('settings.admission-types-edit', ['admissionType' => $tipo])
        ->call('deleteCustomField', $foreignField->id)
        ->assertStatus(403);

    expect(AdmissionTypeCustomField::withoutGlobalScopes()->find($foreignField->id))->not->toBeNull();
});
