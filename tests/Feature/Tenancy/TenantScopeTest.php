<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;

test('queries are scoped to the authenticated user hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    Patient::factory()->create(['hospital_id' => $hospitalA->id]);
    Patient::factory()->create(['hospital_id' => $hospitalB->id]);

    $userA = User::factory()->create(['hospital_id' => $hospitalA->id]);
    $this->actingAs($userA);

    expect(Patient::count())->toBe(1);
});

test('creating a model auto-assigns the current hospital', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    $patient = Patient::create([
        'primer_apellido' => 'Gomez',
        'primer_nombre' => 'Ana',
    ]);

    expect($patient->hospital_id)->toBe($hospital->id);
});

test('super admin without hospital sees all tenants', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    Patient::factory()->create(['hospital_id' => $hospitalA->id]);
    Patient::factory()->create(['hospital_id' => $hospitalB->id]);

    $sa = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $this->actingAs($sa);

    expect(Patient::count())->toBe(2);
});
