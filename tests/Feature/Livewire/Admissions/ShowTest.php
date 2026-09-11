<?php

use App\Models\Admission;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('show admission renders with valid qr token', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $admission = Admission::factory()->create([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'qr_token' => 'valid-token-123',
    ]);
    $this->actingAs($user);

    Livewire::withQueryParams(['token' => 'valid-token-123']);

    Volt::test('admissions.show', ['admission' => $admission])
        ->assertOk()
        ->assertSee($patient->nombreCompleto());
});

test('show admission rejects invalid qr token', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $admission = Admission::factory()->create([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'qr_token' => 'valid-token-123',
    ]);
    $this->actingAs($user);

    Livewire::withQueryParams(['token' => 'invalid-token']);

    Volt::test('admissions.show', ['admission' => $admission])
        ->assertForbidden();
});

test('show admission rejects missing qr token', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $admission = Admission::factory()->create([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'qr_token' => 'valid-token-123',
    ]);
    $this->actingAs($user);

    Volt::test('admissions.show', ['admission' => $admission])
        ->assertForbidden();
});
