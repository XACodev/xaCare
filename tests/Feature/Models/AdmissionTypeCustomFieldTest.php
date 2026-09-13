<?php

use App\Models\AdmissionType;
use App\Models\AdmissionTypeCustomField;
use App\Models\Hospital;
use App\Models\User;

it('scopes custom fields to the owning hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    AdmissionTypeCustomField::factory()->for($hospitalA)->create(['label' => 'Campo A']);
    AdmissionTypeCustomField::factory()->for($hospitalB)->create(['label' => 'Campo B']);

    $user = User::factory()->for($hospitalA)->create();
    $this->actingAs($user);

    expect(AdmissionTypeCustomField::pluck('label')->all())->toBe(['Campo A']);
});

it('casts options as an array for selection fields', function () {
    $hospital = Hospital::factory()->create();
    $tipo = AdmissionType::factory()->for($hospital)->create();

    $field = AdmissionTypeCustomField::create([
        'hospital_id' => $hospital->id,
        'admission_type_id' => $tipo->id,
        'step' => 2,
        'label' => 'Aseguradora',
        'slug' => 'aseguradora',
        'field_type' => 'seleccion_unica',
        'options' => ['Seguros G&T', 'Seguros El Roble', 'Otro'],
        'required' => true,
        'sort_order' => 0,
        'active' => true,
    ]);

    expect($field->fresh()->options)->toBe(['Seguros G&T', 'Seguros El Roble', 'Otro']);
});
