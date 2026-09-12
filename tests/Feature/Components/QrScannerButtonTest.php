<?php

use App\Models\Admission;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

test('el boton de escanear aparece en el indice de cotizaciones', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $this->actingAs($user);

    Volt::test('qxlog.quotes.index')->assertSeeHtml('data-qr-scanner-trigger');
});

test('el boton de escanear aparece en la ficha de expediente', function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

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
        ->assertSeeHtml('data-qr-scanner-trigger');
});
