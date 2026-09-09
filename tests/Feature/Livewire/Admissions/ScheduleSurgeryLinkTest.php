<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'surgeries.schedule', 'guard_name' => 'web']);
});

test('tras registrar un ingreso marcado va_a_quirofano, expone el paciente para el acceso directo', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    $component = Volt::test('admissions.create')
        ->call('selectPatient', $patient->id)
        ->set('va_a_quirofano', true)
        ->set('fecha_ingreso', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect($component->get('last_admission_patient_id'))->toBe($patient->id);
});

test('el formulario de programar cirugia precarga el paciente recibido por query string', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $user->givePermissionTo('surgeries.schedule');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    $this->get(route('surgeries.schedule.create', ['patient_id' => $patient->id]))
        ->assertOk()
        ->assertSee($patient->nombreCompleto());
});

test('ignora un patient_id de otro hospital en la query string', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $user->givePermissionTo('surgeries.schedule');
    $foreignPatient = Patient::factory()->create(['hospital_id' => $otherHospital->id]);
    $this->actingAs($user);

    $this->get(route('surgeries.schedule.create', ['patient_id' => $foreignPatient->id]))
        ->assertOk()
        ->assertDontSee($foreignPatient->nombreCompleto());
});
