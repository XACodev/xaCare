<?php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgeryStatus;
use App\Modules\QxLog\Models\SurgicalCase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['surgeries.view', 'surgeries.schedule'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
});

function boardActingUser(Hospital $hospital, array $permissions = ['surgeries.view']): User
{
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $user->givePermissionTo($permissions);

    return $user;
}

test('aborta sin permiso surgeries.view', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    test()->actingAs($user);

    Volt::test('qxlog.surgeries.board')->assertStatus(403);
});

test('la lista incluye borradores', function () {
    $hospital = Hospital::factory()->create();
    $user = boardActingUser($hospital);
    test()->actingAs($user);

    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'is_draft' => true]);
    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'is_draft' => false]);

    $component = Volt::test('qxlog.surgeries.board')->set('view', 'list');

    expect($component->instance()->cases)->toHaveCount(2);
});

test('kanban y calendario excluyen borradores', function () {
    $hospital = Hospital::factory()->create();
    $user = boardActingUser($hospital);
    test()->actingAs($user);

    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'is_draft' => true]);
    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'is_draft' => false]);

    $kanban = Volt::test('qxlog.surgeries.board')->set('view', 'kanban');
    expect($kanban->instance()->cases)->toHaveCount(1);

    $calendar = Volt::test('qxlog.surgeries.board')->set('view', 'calendar');
    expect($calendar->instance()->cases)->toHaveCount(1);
});

test('kanban agrupa por estado en orden', function () {
    $hospital = Hospital::factory()->create();
    $user = boardActingUser($hospital);
    test()->actingAs($user);

    SurgeryStatus::withoutGlobalScopes()->where('hospital_id', $hospital->id)->delete();
    $statusA = SurgeryStatus::factory()->create(['hospital_id' => $hospital->id, 'sort_order' => 0]);
    $statusB = SurgeryStatus::factory()->create(['hospital_id' => $hospital->id, 'sort_order' => 1]);

    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'surgery_status_id' => $statusA->id, 'is_draft' => false]);
    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'surgery_status_id' => $statusB->id, 'is_draft' => false]);

    $component = Volt::test('qxlog.surgeries.board')->set('view', 'kanban');
    $columns = $component->instance()->casesByStatus;

    expect($columns)->toHaveCount(2)
        ->and($columns->first()['status']->id)->toBe($statusA->id);
});

test('mover una tarjeta cambia el estado con permiso surgeries.schedule', function () {
    $hospital = Hospital::factory()->create();
    $user = boardActingUser($hospital, ['surgeries.view', 'surgeries.schedule']);
    test()->actingAs($user);

    $statusA = SurgeryStatus::factory()->create(['hospital_id' => $hospital->id]);
    $statusB = SurgeryStatus::factory()->create(['hospital_id' => $hospital->id]);
    $case = SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'surgery_status_id' => $statusA->id, 'is_draft' => false]);

    Volt::test('qxlog.surgeries.board')
        ->call('moveToStatus', $case->id, $statusB->id)
        ->assertHasNoErrors();

    expect($case->fresh()->surgery_status_id)->toBe($statusB->id);
});

test('rechaza mover una tarjeta sin permiso surgeries.schedule', function () {
    $hospital = Hospital::factory()->create();
    $user = boardActingUser($hospital, ['surgeries.view']);
    test()->actingAs($user);

    $statusA = SurgeryStatus::factory()->create(['hospital_id' => $hospital->id]);
    $statusB = SurgeryStatus::factory()->create(['hospital_id' => $hospital->id]);
    $case = SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'surgery_status_id' => $statusA->id, 'is_draft' => false]);

    Volt::test('qxlog.surgeries.board')
        ->call('moveToStatus', $case->id, $statusB->id)
        ->assertStatus(403);

    expect($case->fresh()->surgery_status_id)->toBe($statusA->id);
});

test('filtra por quirofano', function () {
    $hospital = Hospital::factory()->create();
    $user = boardActingUser($hospital);
    test()->actingAs($user);

    $roomA = OperatingRoom::factory()->create(['hospital_id' => $hospital->id]);
    $roomB = OperatingRoom::factory()->create(['hospital_id' => $hospital->id]);

    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'operating_room_id' => $roomA->id, 'is_draft' => false]);
    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'operating_room_id' => $roomB->id, 'is_draft' => false]);

    $component = Volt::test('qxlog.surgeries.board')->set('view', 'list')->set('room_filter', $roomA->id);

    expect($component->instance()->cases)->toHaveCount(1);
});

test('no muestra cirugias de otro hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $user = boardActingUser($hospital);
    test()->actingAs($user);

    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'is_draft' => false]);
    SurgicalCase::factory()->create(['hospital_id' => $otherHospital->id, 'is_draft' => false]);

    $component = Volt::test('qxlog.surgeries.board')->set('view', 'list');

    expect($component->instance()->cases)->toHaveCount(1);
});
