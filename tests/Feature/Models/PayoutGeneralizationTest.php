<?php

use App\Models\Hospital;
use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use App\Models\SurgicalAssignment;
use App\Models\User;

test('a payout batch can belong to any payee, not just instrumentists', function () {
    $hospital = Hospital::factory()->create();
    $this->actingAs(User::factory()->create(['hospital_id' => $hospital->id]));

    $surgeon = User::factory()->create(['hospital_id' => $hospital->id]);
    $batch = PayoutBatch::factory()->create(['hospital_id' => $hospital->id, 'payee_id' => $surgeon->id]);
    $assignment = SurgicalAssignment::factory()->create(['hospital_id' => $hospital->id, 'user_id' => $surgeon->id]);
    $item = PayoutItem::factory()->create([
        'hospital_id' => $hospital->id,
        'payout_batch_id' => $batch->id,
        'surgical_assignment_id' => $assignment->id,
    ]);

    expect($batch->payee->id)->toBe($surgeon->id)
        ->and($item->surgicalAssignment->id)->toBe($assignment->id)
        ->and($batch->items->first()->id)->toBe($item->id);
});
