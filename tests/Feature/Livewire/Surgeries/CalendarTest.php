<?php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgicalCase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'surgeries.view', 'guard_name' => 'web']);
});

function calendarActingUser(Hospital $hospital): User
{
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $user->givePermissionTo('surgeries.view');

    return $user;
}

test('aborta sin permiso surgeries.view', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    test()->actingAs($user);

    Volt::test('qxlog.surgeries.calendar')->assertStatus(403);
});

test('agrupa los casos de la semana por dia y hora', function () {
    $hospital = Hospital::factory()->create();
    $user = calendarActingUser($hospital);
    test()->actingAs($user);

    $room = OperatingRoom::factory()->create(['hospital_id' => $hospital->id]);

    SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id, 'is_draft' => false, 'operating_room_id' => $room->id,
        'procedure_date' => '2026-09-10', 'start_time' => '09:00',
    ]);
    SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id, 'is_draft' => false, 'operating_room_id' => $room->id,
        'procedure_date' => '2026-09-05', 'start_time' => '09:00',
    ]);

    $component = Volt::test('qxlog.surgeries.calendar')->set('anchor', '2026-09-10'); // jueves

    [$start, $end] = $component->instance()->weekRange;
    expect($start->toDateString())->toBe('2026-09-07')
        ->and($end->toDateString())->toBe('2026-09-13');

    $day = \Illuminate\Support\Carbon::parse('2026-09-10');
    expect($component->instance()->casesFor($day, 9))->toHaveCount(1)
        ->and($component->instance()->casesFor($day, 10))->toHaveCount(0);
});

test('borradores no aparecen en el calendario semanal', function () {
    $hospital = Hospital::factory()->create();
    $user = calendarActingUser($hospital);
    test()->actingAs($user);

    SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id, 'is_draft' => true, 'procedure_date' => now(),
    ]);

    $component = Volt::test('qxlog.surgeries.calendar');

    expect($component->instance()->cases->flatten(1))->toHaveCount(0);
});

test('semana avanza y retrocede', function () {
    $hospital = Hospital::factory()->create();
    $user = calendarActingUser($hospital);
    test()->actingAs($user);

    $component = Volt::test('qxlog.surgeries.calendar')->set('anchor', '2026-09-10');

    $component->call('nextWeek');
    expect($component->get('anchor'))->toBe('2026-09-17');

    $component->call('prevWeek');
    expect($component->get('anchor'))->toBe('2026-09-10');
});

test('no muestra cirugias de otro hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $user = calendarActingUser($hospital);
    test()->actingAs($user);

    SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'is_draft' => false, 'procedure_date' => now(), 'start_time' => '09:00']);
    SurgicalCase::factory()->create(['hospital_id' => $otherHospital->id, 'is_draft' => false, 'procedure_date' => now(), 'start_time' => '09:00']);

    $component = Volt::test('qxlog.surgeries.calendar');

    expect($component->instance()->cases->flatten(1))->toHaveCount(1);
});
