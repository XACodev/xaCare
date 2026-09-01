<?php

use App\Models\Hospital;
use App\Models\SurgicalRole;

test('surgical role belongs to its hospital and is scoped by tenant', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $roleA = SurgicalRole::factory()->for($hospitalA, 'hospital')->create(['name' => 'Cirujano']);
    SurgicalRole::factory()->for($hospitalB, 'hospital')->create(['name' => 'Cirujano']);

    $this->actingAs(\App\Models\User::factory()->create(['hospital_id' => $hospitalA->id]));

    expect(SurgicalRole::all())->toHaveCount(1)
        ->and(SurgicalRole::first()->id)->toBe($roleA->id)
        ->and($roleA->hospital->id)->toBe($hospitalA->id);
});

test('slug is derived from name when not provided', function () {
    $hospital = Hospital::factory()->create();
    $this->actingAs(\App\Models\User::factory()->create(['hospital_id' => $hospital->id]));

    $role = SurgicalRole::create(['hospital_id' => $hospital->id, 'name' => 'Ayudante de Cirugía']);

    expect($role->slug)->toBe('ayudante-de-cirugia');
});
