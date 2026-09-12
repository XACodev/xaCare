<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('admin can view patient edit screen', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    $this->get(route('patients.edit', $patient))
        ->assertOk()
        ->assertSee($patient->nombreCompleto());
});

test('admin can update patient data', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id, 'telefono' => '11111111']);
    $this->actingAs($user);

    Volt::test('patients.edit', ['patient' => $patient->slug])
        ->set('telefono', '22222222')
        ->set('nombre_padre', 'Juan Padre')
        ->call('save')
        ->assertHasNoErrors();

    $patient->refresh();
    expect($patient->telefono)->toBe('22222222');
    expect($patient->nombre_padre)->toBe('Juan Padre');
});

test('nationality defaults to guatemalan on patient edit', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id, 'nacionalidad' => null]);
    $this->actingAs($user);

    Volt::test('patients.edit', ['patient' => $patient->slug])
        ->set('es_extranjero', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($patient->fresh()->nacionalidad)->toBe('Guatemalteco/a');
});

test('non admin cannot access patient edit screen', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'user']);
    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    $this->get(route('patients.edit', $patient))->assertUnauthorized();
});
