<?php

use App\Models\Hospital;
use App\Modules\QxLog\Models\PayoutBatch;
use App\Modules\QxLog\Models\PayoutItem;
use App\Models\PricingSetting;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalRole;
use App\Models\User;

test('payout batches and items are scoped to the authenticated user hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $batchA = PayoutBatch::factory()->create(['hospital_id' => $hospitalA->id]);
    PayoutBatch::factory()->create(['hospital_id' => $hospitalB->id]);

    PayoutItem::create([
        'hospital_id' => $hospitalA->id,
        'payout_batch_id' => $batchA->id,
        'surgical_assignment_id' => SurgicalAssignment::factory()
            ->for(SurgicalRole::factory()->create(['hospital_id' => $hospitalA->id]), 'surgicalRole')
            ->create(['hospital_id' => $hospitalA->id])->id,
        'amount' => 100,
        'snapshot' => [],
    ]);

    $userA = User::factory()->create(['hospital_id' => $hospitalA->id]);
    $this->actingAs($userA);

    expect(PayoutBatch::count())->toBe(1);
    expect(PayoutItem::count())->toBe(1);
});

test('creating a payout batch auto-assigns the current hospital', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($user);

    $batch = PayoutBatch::create([
        'payee_id' => User::factory()->create(['hospital_id' => $hospital->id])->id,
        'paid_by_id' => $user->id,
        'paid_at' => now(),
        'total_amount' => 500,
        'status' => 'paid',
    ]);

    expect($batch->hospital_id)->toBe($hospital->id);
});

test('pricing settings are scoped per hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $userA = User::factory()->create(['hospital_id' => $hospitalA->id]);
    $this->actingAs($userA);
    $settingsA = PricingSetting::current();
    $settingsA->update(['default_rate' => 111]);

    $userB = User::factory()->create(['hospital_id' => $hospitalB->id]);
    $this->actingAs($userB);
    $settingsB = PricingSetting::current();

    expect($settingsA->hospital_id)->toBe($hospitalA->id);
    expect($settingsB->hospital_id)->toBe($hospitalB->id);
    expect($settingsB->id)->not->toBe($settingsA->id);
    expect((float) $settingsB->default_rate)->not->toBe(111.0);
});
