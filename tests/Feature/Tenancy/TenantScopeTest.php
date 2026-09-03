<?php

use App\Models\Hospital;
use App\Models\HospitalInvitation;
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

test('creating a tenant model without hospital_id and without authenticated hospital aborts', function () {
    $sa = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $this->actingAs($sa);

    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    Patient::create([
        'primer_apellido' => 'Gomez',
        'primer_nombre' => 'Ana',
    ]);
});

test('platform admin can create a tenant model with explicit hospital_id', function () {
    $hospital = Hospital::factory()->create();
    $sa = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $this->actingAs($sa);

    $invitation = HospitalInvitation::create([
        'hospital_id' => $hospital->id,
        'token' => 'test-token',
        'expires_at' => now()->addDay(),
    ]);

    expect($invitation->hospital_id)->toBe($hospital->id);
});
