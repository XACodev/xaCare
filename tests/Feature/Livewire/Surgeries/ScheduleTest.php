<?php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgeryStatus;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\SurgicalRole;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['surgeries.schedule', 'surgeries.cancel', 'surgeries.delete', 'surgeries.view'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
});

function scheduleActingUser(Hospital $hospital, array $permissions = ['surgeries.schedule']): User
{
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $user->givePermissionTo($permissions);

    return $user;
}

test('aborta sin permiso surgeries.schedule', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id]);

    test()->actingAs($user);

    Volt::test('qxlog.surgeries.schedule')->assertStatus(403);
});

test('guarda un borrador con datos minimos sin validar choque de quirofano', function () {
    $hospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital);
    test()->actingAs($user);

    OperatingRoom::factory()->create(['hospital_id' => $hospital->id]);

    Volt::test('qxlog.surgeries.schedule')
        ->set('procedure_date', '2026-11-01')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $case = SurgicalCase::latest('id')->first();

    expect($case)->not->toBeNull()
        ->and($case->is_draft)->toBeTrue();
});

test('programa una cirugia completa y la deja publicada', function () {
    $hospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital);
    test()->actingAs($user);

    $room = OperatingRoom::factory()->create(['hospital_id' => $hospital->id]);
    $status = SurgeryStatus::query()->where('hospital_id', $hospital->id)->where('is_default', true)->firstOrFail();

    Volt::test('qxlog.surgeries.schedule')
        ->set('procedure_date', '2026-11-01')
        ->set('start_time', '08:00')
        ->set('end_time', '10:00')
        ->set('procedure_type', 'Apendicectomia')
        ->set('patient_query', 'Paciente Emergencia')
        ->set('operating_room_id', $room->id)
        ->call('schedule')
        ->assertHasNoErrors();

    $case = SurgicalCase::latest('id')->first();

    expect($case)->not->toBeNull()
        ->and($case->is_draft)->toBeFalse()
        ->and($case->operating_room_id)->toBe($room->id)
        ->and($case->surgery_status_id)->toBe($status->id);
});

test('rechaza programar con choque de quirofano', function () {
    $hospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital);
    test()->actingAs($user);

    $room = OperatingRoom::factory()->create(['hospital_id' => $hospital->id]);
    SurgeryStatus::factory()->create(['hospital_id' => $hospital->id, 'is_default' => true]);

    SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'operating_room_id' => $room->id,
        'procedure_date' => '2026-11-01',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'is_draft' => false,
        'status' => 'scheduled',
    ]);

    Volt::test('qxlog.surgeries.schedule')
        ->set('procedure_date', '2026-11-01')
        ->set('start_time', '09:00')
        ->set('end_time', '11:00')
        ->set('procedure_type', 'Apendicectomia')
        ->set('patient_query', 'Paciente Emergencia')
        ->set('operating_room_id', $room->id)
        ->call('schedule')
        ->assertHasErrors(['operating_room_id']);
});

test('un hospital recien creado ya tiene quirofano por defecto y rechaza el choque sin configuracion manual', function () {
    // Regresion: Hospital::booted() debe sembrar un OperatingRoom/SurgeryStatus por defecto
    // al crear el hospital (ver Hospital::seedDefaultFor). Sin eso, mount() no encuentra
    // ningun quirofano, operating_room_id se guarda null y OperatingRoomAvailabilityService
    // nunca llega a evaluarse — el choque de horario queda deshabilitado en silencio.
    $hospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital);
    test()->actingAs($user);

    Volt::test('qxlog.surgeries.schedule')
        ->set('procedure_date', '2026-11-01')
        ->set('start_time', '08:00')
        ->set('end_time', '10:00')
        ->set('procedure_type', 'Apendicectomia')
        ->set('patient_query', 'Paciente Emergencia')
        ->call('schedule')
        ->assertHasNoErrors();

    $first = SurgicalCase::latest('id')->first();
    expect($first->operating_room_id)->not->toBeNull();

    Volt::test('qxlog.surgeries.schedule')
        ->set('procedure_date', '2026-11-01')
        ->set('start_time', '09:00')
        ->set('end_time', '11:00')
        ->set('procedure_type', 'Colecistectomia')
        ->set('patient_query', 'Paciente Emergencia Dos')
        ->call('schedule')
        ->assertHasErrors(['operating_room_id']);
});

test('oculta el selector de quirofano cuando el hospital tiene uno solo activo', function () {
    $hospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital);
    test()->actingAs($user);

    OperatingRoom::withoutGlobalScopes()->where('hospital_id', $hospital->id)->delete();
    OperatingRoom::factory()->create(['hospital_id' => $hospital->id, 'active' => true]);

    $component = Volt::test('qxlog.surgeries.schedule');

    expect($component->instance()->showRoomSelector)->toBeFalse();
});

test('muestra el selector de quirofano cuando el hospital tiene mas de uno activo', function () {
    $hospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital);
    test()->actingAs($user);

    OperatingRoom::factory()->create(['hospital_id' => $hospital->id, 'active' => true]);
    OperatingRoom::factory()->create(['hospital_id' => $hospital->id, 'active' => true]);

    $component = Volt::test('qxlog.surgeries.schedule');

    expect($component->instance()->showRoomSelector)->toBeTrue();
});

test('edita una cirugia ya programada', function () {
    $hospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital);
    test()->actingAs($user);

    $room = OperatingRoom::factory()->create(['hospital_id' => $hospital->id]);
    $case = SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'operating_room_id' => $room->id,
        'procedure_date' => '2026-11-01',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'procedure_type' => 'Apendicectomia',
        'is_draft' => false,
        'status' => 'scheduled',
    ]);

    Volt::test('qxlog.surgeries.schedule', ['surgery' => $case])
        ->assertSet('procedure_type', 'Apendicectomia')
        ->set('procedure_type', 'Colecistectomia')
        ->call('schedule')
        ->assertHasNoErrors();

    expect($case->fresh()->procedure_type)->toBe('Colecistectomia');
});

test('permite editar la misma cirugia en su propio horario sin falso choque', function () {
    $hospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital);
    test()->actingAs($user);

    $room = OperatingRoom::factory()->create(['hospital_id' => $hospital->id]);
    $case = SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'operating_room_id' => $room->id,
        'procedure_date' => '2026-11-01',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'procedure_type' => 'Apendicectomia',
        'is_draft' => false,
        'status' => 'scheduled',
    ]);

    Volt::test('qxlog.surgeries.schedule', ['surgery' => $case])
        ->call('schedule')
        ->assertHasNoErrors();
});

test('cancela una cirugia programada con permiso surgeries.cancel', function () {
    $hospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital, ['surgeries.schedule', 'surgeries.cancel']);
    test()->actingAs($user);

    SurgeryStatus::withoutGlobalScopes()->where('hospital_id', $hospital->id)->where('is_cancelled', true)->delete();
    $cancelledStatus = SurgeryStatus::factory()->create(['hospital_id' => $hospital->id, 'is_cancelled' => true]);
    $case = SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'status' => 'scheduled',
        'is_draft' => false,
    ]);

    Volt::test('qxlog.surgeries.schedule', ['surgery' => $case])
        ->call('cancel')
        ->assertHasNoErrors();

    expect($case->fresh()->surgery_status_id)->toBe($cancelledStatus->id);
});

test('rechaza cancelar sin permiso surgeries.cancel', function () {
    $hospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital, ['surgeries.schedule']);
    test()->actingAs($user);

    SurgeryStatus::factory()->create(['hospital_id' => $hospital->id, 'is_cancelled' => true]);
    $case = SurgicalCase::factory()->create(['hospital_id' => $hospital->id]);

    Volt::test('qxlog.surgeries.schedule', ['surgery' => $case])
        ->call('cancel')
        ->assertStatus(403);
});

test('elimina (soft delete) con permiso surgeries.delete', function () {
    $hospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital, ['surgeries.schedule', 'surgeries.delete']);
    test()->actingAs($user);

    $case = SurgicalCase::factory()->create(['hospital_id' => $hospital->id]);

    Volt::test('qxlog.surgeries.schedule', ['surgery' => $case])
        ->call('delete');

    expect(SurgicalCase::withTrashed()->find($case->id)->trashed())->toBeTrue();
});

test('rechaza eliminar sin permiso surgeries.delete', function () {
    $hospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital, ['surgeries.schedule']);
    test()->actingAs($user);

    $case = SurgicalCase::factory()->create(['hospital_id' => $hospital->id]);

    Volt::test('qxlog.surgeries.schedule', ['surgery' => $case])
        ->call('delete')
        ->assertStatus(403);
});

test('rechaza un quirofano de otro hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital);
    test()->actingAs($user);

    $foreignRoom = OperatingRoom::factory()->create(['hospital_id' => $otherHospital->id]);

    Volt::test('qxlog.surgeries.schedule')
        ->set('procedure_date', '2026-11-01')
        ->set('start_time', '08:00')
        ->set('end_time', '10:00')
        ->set('procedure_type', 'Apendicectomia')
        ->set('operating_room_id', $foreignRoom->id)
        ->call('schedule')
        ->assertHasErrors(['operating_room_id']);
});

test('agrega staff tentativo sin calculo de pago', function () {
    $hospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital);
    test()->actingAs($user);

    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create();

    Volt::test('qxlog.surgeries.schedule')
        ->set('procedure_date', '2026-11-01')
        ->set('start_time', '08:00')
        ->set('end_time', '10:00')
        ->set('procedure_type', 'Apendicectomia')
        ->set('assignments.0.role_id', $role->id)
        ->set('assignments.0.user_id', $user->id)
        ->call('schedule')
        ->assertHasNoErrors();

    $case = SurgicalCase::latest('id')->first();

    expect($case->assignments)->toHaveCount(1)
        ->and($case->assignments->first()->calculated_amount)->toEqual(0);
});

test('selecting a patient closes the patient suggestions dropdown', function () {
    $hospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital);
    test()->actingAs($user);

    $patient = \App\Models\Patient::factory()->for($hospital, 'hospital')->create([
        'primer_nombre' => 'Maria',
        'primer_apellido' => 'Lopez',
    ]);

    $component = Volt::test('qxlog.surgeries.schedule')
        ->set('patient_query', 'Maria');

    expect($component->get('patient_suggestions'))->not->toBeEmpty();

    $component->call('selectPatient', $patient->id);

    expect($component->get('patient_suggestions'))->toBeEmpty();
});

test('selecting a person closes that assignment row suggestions dropdown', function () {
    $hospital = Hospital::factory()->create();
    $user = scheduleActingUser($hospital);
    test()->actingAs($user);

    $candidate = User::factory()->create(['hospital_id' => $hospital->id, 'name' => 'Carlos Ramirez', 'role' => '']);

    $component = Volt::test('qxlog.surgeries.schedule')
        ->set('assignments.0.user_query', 'Carlos');

    expect(($component->instance()->userSuggestions)('Carlos', null))->not->toBeEmpty();

    $component->call('selectAssignmentUser', 0, $candidate->id);

    $row = $component->get('assignments')[0];

    expect(($component->instance()->userSuggestions)($row['user_query'], $row['user_id']))->toBeEmpty();
});
