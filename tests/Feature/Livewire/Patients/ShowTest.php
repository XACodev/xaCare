<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('hospital admin can view full patient details', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $this->actingAs($user);

    $patient = Patient::factory()->create([
        'hospital_id' => $hospital->id,
        'dpi' => '1234567890101',
        'telefono' => '55551234',
    ]);

    Volt::test('patients.show', ['patient' => $patient])
        ->assertSee($patient->dpi)
        ->assertSee($patient->telefono);
});

test('platform admin can view patient details read-only', function () {
    $hospital = Hospital::factory()->create();
    $patient = Patient::factory()->create([
        'hospital_id' => $hospital->id,
        'dpi' => '9988776655443',
    ]);

    $platformAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $this->actingAs($platformAdmin);

    Volt::test('patients.show', ['patient' => $patient])
        ->assertSee($patient->dpi);
});

test('user without admin role cannot view patient details', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);

    Volt::test('patients.show', ['patient' => $patient])
        ->assertForbidden();
});
