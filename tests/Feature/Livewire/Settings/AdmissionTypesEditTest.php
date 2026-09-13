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

test('elimina un campo personalizado del tipo', function () {
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

    expect(AdmissionTypeCustomField::withoutGlobalScopes()->find($field->id))->toBeNull();
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
