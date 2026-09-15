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

test('a patient readmitted multiple times in the same month shows every admission in the list', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id, 'primer_nombre' => 'Reingresa', 'primer_apellido' => 'Seguido']);

    Admission::factory()->create(['hospital_id' => $hospital->id, 'patient_id' => $patient->id, 'fecha_ingreso' => now()->startOfMonth()->addDays(2)]);
    Admission::factory()->create(['hospital_id' => $hospital->id, 'patient_id' => $patient->id, 'fecha_ingreso' => now()->startOfMonth()->addDays(10)]);
    Admission::factory()->create(['hospital_id' => $hospital->id, 'patient_id' => $patient->id, 'fecha_ingreso' => now()->startOfMonth()->addDays(18)]);

    $this->actingAs($user);

    Volt::test('admissions.index')
        ->set('period', 'month')
        ->assertSee('Reingresa Seguido');

    expect($patient->admissions()->count())->toBe(3);
});

test('period filter narrows the admissions list to today', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $oldPatient = Patient::factory()->create(['hospital_id' => $hospital->id, 'primer_nombre' => 'Delmespasado', 'primer_apellido' => 'X']);
    $todayPatient = Patient::factory()->create(['hospital_id' => $hospital->id, 'primer_nombre' => 'Dehoy', 'primer_apellido' => 'Y']);

    Admission::factory()->create(['hospital_id' => $hospital->id, 'patient_id' => $oldPatient->id, 'fecha_ingreso' => now()->subMonth()]);
    Admission::factory()->create(['hospital_id' => $hospital->id, 'patient_id' => $todayPatient->id, 'fecha_ingreso' => now()]);

    $this->actingAs($user);

    Volt::test('admissions.index')
        ->set('period', 'day')
        ->assertSee($todayPatient->nombreCompleto())
        ->assertDontSee($oldPatient->nombreCompleto());
});

test('mobile card meta omits empty separators when admission type is missing', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create([
        'hospital_id' => $hospital->id,
        'primer_nombre' => 'Sintipo',
        'segundo_nombre' => null,
        'primer_apellido' => 'Vacio',
        'segundo_apellido' => null,
        'dpi' => null,
        'expediente_no' => null,
    ]);

    Admission::factory()->create([
        'hospital_id' => $hospital->id,
        'patient_id' => $patient->id,
        'admission_type_id' => null,
        'sala_ingreso' => null,
        'fecha_ingreso' => now(),
        'total_dias' => null,
    ]);

    $this->actingAs($user);

    Volt::test('admissions.index')
        ->set('period', 'month')
        ->assertSee('Sintipo Vacio')
        ->assertDontSee(' · · ');
});
