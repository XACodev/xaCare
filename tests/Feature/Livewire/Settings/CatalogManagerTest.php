<?php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\SurgicalRole;
use Livewire\Volt\Volt;

test('lista solo catalogos del hospital del usuario, crea, edita, activa/desactiva y reordena', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);
    $this->actingAs($admin);

    $roleA = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Cirujano', 'sort_order' => 0]);
    $roleB = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Circulante', 'sort_order' => 1]);
    SurgicalRole::factory()->for($otherHospital, 'hospital')->create(['name' => 'De otro hospital']);

    $component = Volt::test('qxlog.settings.roles');

    expect($component->instance()->items)->toHaveCount(2);

    $component->set('form.name', 'Anestesiólogo')
        ->call('create')
        ->assertHasNoErrors();
    expect(SurgicalRole::where('hospital_id', $hospital->id)->where('name', 'Anestesiólogo')->exists())->toBeTrue();

    $component->call('toggleActive', $roleB->id);
    expect($roleB->fresh()->active)->toBeFalse();

    $component->call('moveDown', $roleA->id);
    expect($roleA->fresh()->sort_order)->toBeGreaterThan($roleB->fresh()->sort_order);
});

test('un admin no puede tocar un catalogo de otro hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);
    $this->actingAs($admin);

    $foreignRole = SurgicalRole::factory()->for($otherHospital, 'hospital')->create();

    Volt::test('qxlog.settings.roles')
        ->call('toggleActive', $foreignRole->id)
        ->assertStatus(403);
});
