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

test('calendario mensual agrupa los casos del mes por dia', function () {
    $hospital = Hospital::factory()->create();
    $user = boardActingUser($hospital);
    test()->actingAs($user);

    $anchor = '2026-09-10';
    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'is_draft' => false, 'procedure_date' => '2026-09-05']);
    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'is_draft' => false, 'procedure_date' => '2026-09-05']);
    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'is_draft' => false, 'procedure_date' => '2026-08-31']);

    $component = Volt::test('qxlog.surgeries.board')->set('view', 'calendar')->set('calendar_anchor', $anchor);

    $days = collect($component->instance()->calendarDays);
    $sep5 = $days->firstWhere(fn ($d) => $d['date']->toDateString() === '2026-09-05');
    $aug31 = $days->firstWhere(fn ($d) => $d['date']->toDateString() === '2026-08-31');

    expect($sep5['cases'])->toHaveCount(2)
        ->and($sep5['in_current_month'])->toBeTrue()
        ->and($aug31['cases'])->toHaveCount(1)
        ->and($aug31['in_current_month'])->toBeFalse();
});

test('calendario avanza y retrocede de mes sin desbordar el dia', function () {
    $hospital = Hospital::factory()->create();
    $user = boardActingUser($hospital);
    test()->actingAs($user);

    $component = Volt::test('qxlog.surgeries.board')
        ->set('view', 'calendar')
        ->set('calendar_anchor', '2026-01-31');

    $component->call('calendarNext');
    expect($component->get('calendar_anchor'))->toBe('2026-02-28');

    $component->call('calendarPrev');
    expect($component->get('calendar_anchor'))->toBe('2026-01-28');
});

test('calendario semanal usa semanas de lunes a domingo', function () {
    $hospital = Hospital::factory()->create();
    $user = boardActingUser($hospital);
    test()->actingAs($user);

    $component = Volt::test('qxlog.surgeries.board')
        ->set('view', 'calendar')
        ->call('setCalendarScale', 'week')
        ->set('calendar_anchor', '2026-09-10'); // jueves

    [$start, $end] = $component->instance()->calendarRange;

    expect($start->toDateString())->toBe('2026-09-07') // lunes
        ->and($end->toDateString())->toBe('2026-09-13'); // domingo
});

test('hoy resetea el ancla del calendario a la fecha actual', function () {
    $hospital = Hospital::factory()->create();
    $user = boardActingUser($hospital);
    test()->actingAs($user);

    $component = Volt::test('qxlog.surgeries.board')
        ->set('view', 'calendar')
        ->set('calendar_anchor', '2020-01-01')
        ->call('calendarToday');

    expect($component->get('calendar_anchor'))->toBe(now()->toDateString());
});

test('el calendario respeta el filtro de quirofano y estado', function () {
    $hospital = Hospital::factory()->create();
    $user = boardActingUser($hospital);
    test()->actingAs($user);

    $roomA = OperatingRoom::factory()->create(['hospital_id' => $hospital->id]);
    $roomB = OperatingRoom::factory()->create(['hospital_id' => $hospital->id]);

    SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id, 'is_draft' => false,
        'operating_room_id' => $roomA->id, 'procedure_date' => '2026-09-10',
    ]);
    SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id, 'is_draft' => false,
        'operating_room_id' => $roomB->id, 'procedure_date' => '2026-09-10',
    ]);

    $component = Volt::test('qxlog.surgeries.board')
        ->set('view', 'calendar')
        ->set('calendar_anchor', '2026-09-10')
        ->set('room_filter', $roomA->id);

    $days = collect($component->instance()->calendarDays);
    $day = $days->firstWhere(fn ($d) => $d['date']->toDateString() === '2026-09-10');

    expect($day['cases'])->toHaveCount(1);
});

test('abrir un dia del calendario expone sus casos y cerrar limpia la seleccion', function () {
    $hospital = Hospital::factory()->create();
    $user = boardActingUser($hospital);
    test()->actingAs($user);

    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'is_draft' => false, 'procedure_date' => '2026-09-10']);

    $component = Volt::test('qxlog.surgeries.board')
        ->set('view', 'calendar')
        ->set('calendar_anchor', '2026-09-10')
        ->call('openCalendarDay', '2026-09-10');

    expect($component->instance()->selectedDayCases)->toHaveCount(1);

    $component->call('closeCalendarDay');

    expect($component->get('calendar_selected_day'))->toBeNull();
});
