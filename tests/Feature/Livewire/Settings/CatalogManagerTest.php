<?php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgeryStatus;
use App\Modules\QxLog\Models\SurgicalRole;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'pricing.manage', 'guard_name' => 'web']);
});

function catalogManagerAdmin(Hospital $hospital): User
{
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);
    $admin->givePermissionTo('pricing.manage');

    return $admin;
}

test('lista solo catalogos del hospital del usuario, crea, edita, activa/desactiva y reordena', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = catalogManagerAdmin($hospital);
    $this->actingAs($admin);

    $roleA = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Cardiólogo', 'sort_order' => 3]);
    $roleB = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Anestesista', 'sort_order' => 4]);
    SurgicalRole::factory()->for($otherHospital, 'hospital')->create(['name' => 'De otro hospital']);

    $component = Volt::test('qxlog.settings.roles');

    expect($component->instance()->items)->toHaveCount(5);

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
    $admin = catalogManagerAdmin($hospital);
    $this->actingAs($admin);

    $foreignRole = SurgicalRole::factory()->for($otherHospital, 'hospital')->create();

    Volt::test('qxlog.settings.roles')
        ->call('toggleActive', $foreignRole->id)
        ->assertStatus(403);
});

test('un admin no puede tocar un estado de cirugia de otro hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = catalogManagerAdmin($hospital);
    $this->actingAs($admin);

    $foreignStatus = SurgeryStatus::factory()->for($otherHospital, 'hospital')->create();

    Volt::test('qxlog.settings.statuses')
        ->call('toggleActive', $foreignStatus->id)
        ->assertStatus(403);
});

test('un admin no puede tocar un quirofano de otro hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = catalogManagerAdmin($hospital);
    $this->actingAs($admin);

    $foreignRoom = OperatingRoom::factory()->for($otherHospital, 'hospital')->create();

    Volt::test('qxlog.settings.rooms')
        ->call('toggleActive', $foreignRoom->id)
        ->assertStatus(403);
});
