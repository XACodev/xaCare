<?php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgeryStatus;
use App\Modules\QxLog\Models\SurgicalCase;

test('surgical case can be linked to an operating room and a surgery status', function () {
    $hospital = Hospital::factory()->create();
    $this->actingAs(User::factory()->create(['hospital_id' => $hospital->id]));

    $room = OperatingRoom::factory()->for($hospital, 'hospital')->create();
    $status = SurgeryStatus::factory()->for($hospital, 'hospital')->create(['name' => 'Programada']);

    $case = SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'operating_room_id' => $room->id,
        'surgery_status_id' => $status->id,
    ]);

    expect($case->operatingRoom->id)->toBe($room->id)
        ->and($case->surgeryStatus->id)->toBe($status->id)
        ->and($case->is_draft)->toBeFalse();
});

test('surgical case defaults to not a draft and can be marked as one', function () {
    $hospital = Hospital::factory()->create();
    $this->actingAs(User::factory()->create(['hospital_id' => $hospital->id]));

    $draft = SurgicalCase::factory()->create(['hospital_id' => $hospital->id, 'is_draft' => true]);

    expect($draft->is_draft)->toBeTrue();
});
