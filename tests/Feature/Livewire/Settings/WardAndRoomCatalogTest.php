<?php

use App\Models\Hospital;
use App\Models\HospitalRoom;
use App\Models\HospitalWard;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'settings.manage', 'guard_name' => 'web']);
});

function wardCatalogAdmin(Hospital $hospital): User
{
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);
    $admin->givePermissionTo('settings.manage');

    return $admin;
}

test('lista solo salas del hospital del usuario, crea, activa/desactiva y reordena', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = wardCatalogAdmin($hospital);
    $this->actingAs($admin);

    $wardA = HospitalWard::factory()->for($hospital, 'hospital')->create(['name' => 'Sala 1', 'sort_order' => 3]);
    $wardB = HospitalWard::factory()->for($hospital, 'hospital')->create(['name' => 'Sala 2', 'sort_order' => 4]);
    HospitalWard::factory()->for($otherHospital, 'hospital')->create(['name' => 'De otro hospital']);

    $component = Volt::test('settings.wards');

    expect($component->instance()->items)->toHaveCount(2);

    $component->set('form.name', 'Emergencia')
        ->call('create')
        ->assertHasNoErrors();
    expect(HospitalWard::where('hospital_id', $hospital->id)->where('name', 'Emergencia')->exists())->toBeTrue();

    $component->call('toggleActive', $wardB->id);
    expect($wardB->fresh()->active)->toBeFalse();

    $component->call('moveDown', $wardA->id);
    expect($wardA->fresh()->sort_order)->toBeGreaterThan($wardB->fresh()->sort_order);
});

test('un admin no puede tocar una sala de otro hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = wardCatalogAdmin($hospital);
    $this->actingAs($admin);

    $foreignWard = HospitalWard::factory()->for($otherHospital, 'hospital')->create();

    Volt::test('settings.wards')
        ->call('toggleActive', $foreignWard->id)
        ->assertStatus(403);
});

test('crear una sala con nombre duplicado (insensible a mayusculas) devuelve error de validacion', function () {
    $hospital = Hospital::factory()->create();
    $admin = wardCatalogAdmin($hospital);
    $this->actingAs($admin);

    HospitalWard::factory()->for($hospital, 'hospital')->create(['name' => 'Sala 3']);

    Volt::test('settings.wards')
        ->set('form.name', 'sala 3')
        ->call('create')
        ->assertHasErrors(['form.name']);

    expect(HospitalWard::withoutGlobalScopes()->where('hospital_id', $hospital->id)->whereRaw('LOWER(name) = ?', ['sala 3'])->count())->toBe(1);
});

test('lista solo habitaciones del hospital del usuario, crea, activa/desactiva y reordena', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = wardCatalogAdmin($hospital);
    $this->actingAs($admin);

    $roomA = HospitalRoom::factory()->for($hospital, 'hospital')->create(['name' => '101', 'sort_order' => 3]);
    $roomB = HospitalRoom::factory()->for($hospital, 'hospital')->create(['name' => '102', 'sort_order' => 4]);
    HospitalRoom::factory()->for($otherHospital, 'hospital')->create(['name' => '999']);

    $component = Volt::test('settings.hospital-rooms');

    expect($component->instance()->items)->toHaveCount(2);

    $component->set('form.name', '103')
        ->call('create')
        ->assertHasNoErrors();
    expect(HospitalRoom::where('hospital_id', $hospital->id)->where('name', '103')->exists())->toBeTrue();

    $component->call('toggleActive', $roomB->id);
    expect($roomB->fresh()->active)->toBeFalse();

    $component->call('moveDown', $roomA->id);
    expect($roomA->fresh()->sort_order)->toBeGreaterThan($roomB->fresh()->sort_order);
});

test('un admin no puede tocar una habitacion de otro hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = wardCatalogAdmin($hospital);
    $this->actingAs($admin);

    $foreignRoom = HospitalRoom::factory()->for($otherHospital, 'hospital')->create();

    Volt::test('settings.hospital-rooms')
        ->call('toggleActive', $foreignRoom->id)
        ->assertStatus(403);
});
