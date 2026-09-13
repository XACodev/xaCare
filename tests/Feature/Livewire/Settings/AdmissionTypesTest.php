<?php

use App\Models\AdmissionType;
use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'settings.manage', 'guard_name' => 'web']);
});

function admissionTypesCatalogAdmin(Hospital $hospital): User
{
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);
    $admin->givePermissionTo('settings.manage');

    return $admin;
}

test('lista solo tipos de ingreso del hospital del usuario, crea, activa/desactiva y reordena', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = admissionTypesCatalogAdmin($hospital);
    $this->actingAs($admin);

    $tipoA = AdmissionType::factory()->for($hospital)->create(['name' => 'Tipo A', 'sort_order' => 3]);
    $tipoB = AdmissionType::factory()->for($hospital)->create(['name' => 'Tipo B', 'sort_order' => 4]);
    AdmissionType::factory()->for($otherHospital)->create(['name' => 'De otro hospital']);

    $component = Volt::test('settings.admission-types');

    expect($component->instance()->items)->toHaveCount(2);

    $component->set('form.name', 'Hospitalización diurna')
        ->call('create')
        ->assertHasNoErrors();

    $created = AdmissionType::where('hospital_id', $hospital->id)->where('name', 'Hospitalización diurna')->first();
    expect($created)->not->toBeNull()
        ->and($created->slug)->toBe('hospitalizacion-diurna');

    $component->assertSee(route('settings.admission-types.edit', $tipoA), false);

    $component->call('toggleActive', $tipoB->id);
    expect($tipoB->fresh()->active)->toBeFalse();

    $component->call('moveDown', $tipoA->id);
    expect($tipoA->fresh()->sort_order)->toBeGreaterThan($tipoB->fresh()->sort_order);
});

test('rechaza como error de formulario un nombre que colisiona en slug con otro existente', function () {
    $hospital = Hospital::factory()->create();
    $admin = admissionTypesCatalogAdmin($hospital);
    $this->actingAs($admin);

    AdmissionType::factory()->for($hospital)->create(['name' => 'Hospitalización', 'slug' => 'hospitalizacion']);

    Volt::test('settings.admission-types')
        ->set('form.name', 'Hospitalizacion')
        ->call('create')
        ->assertHasErrors('form.name');

    expect(AdmissionType::where('hospital_id', $hospital->id)->where('name', 'Hospitalizacion')->exists())->toBeFalse();
});

test('un admin no puede tocar un tipo de ingreso de otro hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = admissionTypesCatalogAdmin($hospital);
    $this->actingAs($admin);

    $foreignTipo = AdmissionType::factory()->for($otherHospital)->create();

    Volt::test('settings.admission-types')
        ->call('toggleActive', $foreignTipo->id)
        ->assertStatus(403);
});
