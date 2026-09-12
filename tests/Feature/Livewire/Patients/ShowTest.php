<?php

use App\Models\Admission;
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

    Volt::test('patients.show', ['patient' => $patient->slug])
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

    Volt::test('patients.show', ['patient' => $patient->slug])
        ->assertSee($patient->dpi);
});

test('hospital admin can view the details of a soft-deleted patient', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');

    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $patient->delete();

    $this->actingAs($user)
        ->get(route('patients.show', $patient))
        ->assertOk()
        ->assertSee($patient->nombreCompleto());
});

test('user without admin role cannot view patient details', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);

    Volt::test('patients.show', ['patient' => $patient->slug])
        ->assertForbidden();
});

test('a patient with several admissions shows historial de ingresos with a sequential numero', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $this->actingAs($user);

    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);

    Admission::factory()->create([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'fecha_ingreso' => now()->subMonths(2),
    ]);
    Admission::factory()->create([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'fecha_ingreso' => now()->subMonth(),
    ]);
    Admission::factory()->create([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'fecha_ingreso' => now(),
    ]);

    Volt::test('patients.show', ['patient' => $patient->slug])
        ->assertSee(__('Historial de ingresos'))
        ->assertSeeInOrder([
            __('Ingreso No.').' 3',
            __('Ingreso No.').' 2',
            __('Ingreso No.').' 1',
        ]);
});
