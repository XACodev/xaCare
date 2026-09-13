<?php

use App\Models\AdmissionType;
use App\Models\AdmissionTypeCustomField;
use App\Models\Hospital;
use App\Models\User;

it('scopes admission types to the authenticated hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    AdmissionType::factory()->for($hospitalA)->create();
    AdmissionType::factory()->for($hospitalB)->create();

    $user = User::factory()->for($hospitalA)->create();
    $this->actingAs($user);

    expect(AdmissionType::count())->toBe(1);
});

it('scopes custom fields to the authenticated hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    AdmissionTypeCustomField::factory()->for($hospitalA)->create();
    AdmissionTypeCustomField::factory()->for($hospitalB)->create();

    $user = User::factory()->for($hospitalA)->create();
    $this->actingAs($user);

    expect(AdmissionTypeCustomField::count())->toBe(1);
});

it('auto-assigns hospital_id on create for both new models', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->for($hospital)->create();
    $this->actingAs($user);

    $tipo = AdmissionType::create(['name' => 'Test', 'slug' => 'test']);

    expect($tipo->hospital_id)->toBe($hospital->id);
});
