<?php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Services\OperatingRoomAvailabilityService;

function makeScheduledCase(OperatingRoom $room, string $date, string $start, string $end, bool $isDraft = false, ?string $status = null): SurgicalCase
{
    return SurgicalCase::factory()->create([
        'operating_room_id' => $room->id,
        'procedure_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
        'is_draft' => $isDraft,
        'status' => $status ?? 'scheduled',
    ]);
}

beforeEach(function () {
    $hospital = Hospital::factory()->create();
    $this->actingAs(User::factory()->create(['hospital_id' => $hospital->id]));
    $this->hospital = $hospital;
});

test('rechaza solapamiento total', function () {
    $room = OperatingRoom::factory()->create();
    makeScheduledCase($room, '2026-10-01', '08:00', '10:00');

    $service = new OperatingRoomAvailabilityService();

    expect($service->isAvailable($room, '2026-10-01', '08:00', '10:00'))->toBeFalse();
});

test('rechaza solapamiento parcial', function () {
    $room = OperatingRoom::factory()->create();
    makeScheduledCase($room, '2026-10-01', '08:00', '10:00');

    $service = new OperatingRoomAvailabilityService();

    expect($service->isAvailable($room, '2026-10-01', '09:00', '11:00'))->toBeFalse();
    expect($service->isAvailable($room, '2026-10-01', '07:00', '09:00'))->toBeFalse();
});

test('acepta horarios adyacentes sin solapamiento', function () {
    $room = OperatingRoom::factory()->create();
    makeScheduledCase($room, '2026-10-01', '08:00', '10:00');

    $service = new OperatingRoomAvailabilityService();

    expect($service->isAvailable($room, '2026-10-01', '10:00', '12:00'))->toBeTrue();
    expect($service->isAvailable($room, '2026-10-01', '06:00', '08:00'))->toBeTrue();
});

test('ignora casos en borrador al validar choque', function () {
    $room = OperatingRoom::factory()->create();
    makeScheduledCase($room, '2026-10-01', '08:00', '10:00', isDraft: true);

    $service = new OperatingRoomAvailabilityService();

    expect($service->isAvailable($room, '2026-10-01', '08:00', '10:00'))->toBeTrue();
});

test('ignora casos cancelados al validar choque', function () {
    $room = OperatingRoom::factory()->create();
    makeScheduledCase($room, '2026-10-01', '08:00', '10:00', status: 'cancelled');

    $service = new OperatingRoomAvailabilityService();

    expect($service->isAvailable($room, '2026-10-01', '08:00', '10:00'))->toBeTrue();
});

test('excluye el propio caso al editar', function () {
    $room = OperatingRoom::factory()->create();
    $case = makeScheduledCase($room, '2026-10-01', '08:00', '10:00');

    $service = new OperatingRoomAvailabilityService();

    expect($service->isAvailable($room, '2026-10-01', '08:00', '10:00', excludeCaseId: $case->id))->toBeTrue();
});

test('ignora quirofanos distintos', function () {
    $room = OperatingRoom::factory()->create();
    $otherRoom = OperatingRoom::factory()->create();
    makeScheduledCase($room, '2026-10-01', '08:00', '10:00');

    $service = new OperatingRoomAvailabilityService();

    expect($service->isAvailable($otherRoom, '2026-10-01', '08:00', '10:00'))->toBeTrue();
});
