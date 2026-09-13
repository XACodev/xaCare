<?php

use App\Models\AdmissionType;
use App\Models\Hospital;
use App\Models\User;
use App\Support\AdmissionTypeSeeder;

it('scopes admission types to the owning hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    AdmissionType::factory()->for($hospitalA)->create(['name' => 'Tipo A']);
    AdmissionType::factory()->for($hospitalB)->create(['name' => 'Tipo B']);

    $user = User::factory()->for($hospitalA)->create();
    $this->actingAs($user);

    expect(AdmissionType::pluck('name')->all())->toBe(['Tipo A']);
});

it('seeds the 7 default admission types for a new hospital', function () {
    $hospital = Hospital::factory()->create();

    AdmissionTypeSeeder::seedDefaultsFor($hospital);

    $slugs = AdmissionType::withoutGlobalScopes()
        ->where('hospital_id', $hospital->id)
        ->orderBy('sort_order')
        ->pluck('slug')
        ->all();

    expect($slugs)->toBe([
        'coex',
        'emergencia-habil',
        'emergencia-inhabil',
        'coex-emergencia',
        'urgencia',
        'hospitalizacion',
        'hospitalizacion-emergencia',
    ]);
});

it('sets visible and required sections for hospitalizacion per spec defaults', function () {
    $hospital = Hospital::factory()->create();

    AdmissionTypeSeeder::seedDefaultsFor($hospital);

    $tipo = AdmissionType::withoutGlobalScopes()
        ->where('hospital_id', $hospital->id)
        ->where('slug', 'hospitalizacion')
        ->firstOrFail();

    expect($tipo->visible_sections)->toBe([
        'nacionalidad_documento',
        'lugar_nacimiento_direccion',
        'estado_civil',
        'familiares',
        'contactos_emergencia',
        'seguro',
        'sala_habitacion',
        'medico_responsable',
    ]);
});
