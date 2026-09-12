<?php

use App\Models\Admission;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Support\AdmissionQr;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('scanning the printed qr resolves to the admission without the qr ever carrying a url', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $admission = Admission::factory()->create([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'qr_token' => 'opaque-token-abc',
    ]);
    $this->actingAs($user);

    $svg = AdmissionQr::svg($admission);
    expect($svg)->not->toContain(route('admissions.show', $admission))
        ->and($svg)->not->toContain(url('/'))
        ->and($svg)->not->toContain('/admissions/');

    $this->get(route('qr.resolve', ['token' => 'opaque-token-abc']))
        ->assertRedirect(route('admissions.show', ['admission' => $admission, 'token' => 'opaque-token-abc']));
});

test('resolving an unknown token 404s instead of leaking route structure', function () {
    $user = User::factory()->create(['hospital_id' => Hospital::factory()->create()->id]);
    $user->assignRole('admin');
    $this->actingAs($user);

    $this->get(route('qr.resolve', ['token' => 'does-not-exist']))->assertNotFound();
});

test('a token belonging to another hospital does not resolve', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    $userA = User::factory()->create(['hospital_id' => $hospitalA->id, 'role' => 'admin']);
    $userA->assignRole('admin');

    $patientB = Patient::factory()->create(['hospital_id' => $hospitalB->id]);
    $admissionB = Admission::factory()->create([
        'hospital_id' => $hospitalB->id,
        'patient_id' => $patientB->id,
        'qr_token' => 'other-hospital-token',
    ]);

    $this->actingAs($userA);

    $this->get(route('qr.resolve', ['token' => 'other-hospital-token']))->assertNotFound();
});
