<?php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\SurgeryStatus;

test('surgery status belongs to its hospital and is scoped by tenant', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    SurgeryStatus::withoutGlobalScopes()->whereIn('hospital_id', [$hospitalA->id, $hospitalB->id])->delete();

    $statusA = SurgeryStatus::factory()->for($hospitalA, 'hospital')->create(['name' => 'Programada']);
    SurgeryStatus::factory()->for($hospitalB, 'hospital')->create(['name' => 'Programada']);

    $this->actingAs(User::factory()->create(['hospital_id' => $hospitalA->id]));

    expect(SurgeryStatus::all())->toHaveCount(1)
        ->and(SurgeryStatus::first()->id)->toBe($statusA->id)
        ->and($statusA->hospital->id)->toBe($hospitalA->id);
});

test('slug is derived from name when not provided', function () {
    $hospital = Hospital::factory()->create();
    SurgeryStatus::withoutGlobalScopes()->where('hospital_id', $hospital->id)->delete();
    $this->actingAs(User::factory()->create(['hospital_id' => $hospital->id]));

    $status = SurgeryStatus::create(['hospital_id' => $hospital->id, 'name' => 'En Curso']);

    expect($status->slug)->toBe('en-curso');
});

test('boolean flags cast correctly', function () {
    $hospital = Hospital::factory()->create();
    SurgeryStatus::withoutGlobalScopes()->where('hospital_id', $hospital->id)->delete();
    $this->actingAs(User::factory()->create(['hospital_id' => $hospital->id]));

    $status = SurgeryStatus::create([
        'hospital_id' => $hospital->id,
        'name' => 'Completada',
        'is_completed' => true,
    ]);

    $status->refresh();

    expect($status->is_completed)->toBeTrue()
        ->and($status->is_cancelled)->toBeFalse()
        ->and($status->is_default)->toBeFalse()
        ->and($status->active)->toBeTrue();
});
