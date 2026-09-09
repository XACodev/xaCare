<?php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\OperatingRoom;

test('operating room belongs to its hospital and is scoped by tenant', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    OperatingRoom::withoutGlobalScopes()->whereIn('hospital_id', [$hospitalA->id, $hospitalB->id])->delete();

    $roomA = OperatingRoom::factory()->for($hospitalA, 'hospital')->create(['name' => 'Quirófano 1']);
    OperatingRoom::factory()->for($hospitalB, 'hospital')->create(['name' => 'Quirófano 1']);

    $this->actingAs(User::factory()->create(['hospital_id' => $hospitalA->id]));

    expect(OperatingRoom::all())->toHaveCount(1)
        ->and(OperatingRoom::first()->id)->toBe($roomA->id)
        ->and($roomA->hospital->id)->toBe($hospitalA->id);
});

test('default_for_procedure_types casts to array', function () {
    $hospital = Hospital::factory()->create();
    $this->actingAs(User::factory()->create(['hospital_id' => $hospital->id]));

    $room = OperatingRoom::create([
        'hospital_id' => $hospital->id,
        'name' => 'Quirófano Principal',
        'is_default' => true,
        'default_for_procedure_types' => ['Apendicectomía', 'Colecistectomía'],
    ]);

    $room->refresh();

    expect($room->default_for_procedure_types)->toBe(['Apendicectomía', 'Colecistectomía'])
        ->and($room->is_default)->toBeTrue()
        ->and($room->active)->toBeTrue();
});
