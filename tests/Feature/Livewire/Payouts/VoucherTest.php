<?php

use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use App\Models\Procedure;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'instrumentist', 'guard_name' => 'web']);

    Permission::create(['name' => 'payouts.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'payouts.create', 'guard_name' => 'web']);
    $adminRole->givePermissionTo(['payouts.view', 'payouts.create']);
});

function makePaidBatchWithItem(User $instrumentist, User $admin): PayoutBatch
{
    $procedure = Procedure::factory()->create([
        'instrumentist_id' => $instrumentist->id,
        'status' => 'paid',
        'calculated_amount' => 100,
    ]);

    $batch = PayoutBatch::factory()->create([
        'instrumentist_id' => $instrumentist->id,
        'paid_by_id' => $admin->id,
        'total_amount' => 100,
    ]);

    PayoutItem::create([
        'payout_batch_id' => $batch->id,
        'procedure_id' => $procedure->id,
        'amount' => 100,
        'snapshot' => [
            'procedure_date' => $procedure->procedure_date,
            'start_time' => $procedure->start_time,
            'end_time' => $procedure->end_time,
            'duration_minutes' => $procedure->duration_minutes,
            'patient_name' => $procedure->patient_name,
            'procedure_type' => $procedure->procedure_type,
            'is_videosurgery' => false,
            'calculated_amount' => 100,
            'pricing_snapshot' => [
                'rule' => 'default_rate',
                'use_pay_scheme' => false,
                'rates' => [
                    'default_rate' => 100,
                    'video_rate' => 150,
                    'night_rate' => 200,
                    'long_case_rate' => 250,
                    'courtesy_rate' => 0,
                ],
            ],
        ],
    ]);

    return $batch;
}

test('shows liquidate again button when instrumentist still has pending procedures', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $instrumentist = User::factory()->create();
    $instrumentist->assignRole('instrumentist');

    $batch = makePaidBatchWithItem($instrumentist, $admin);

    Procedure::factory()->create([
        'instrumentist_id' => $instrumentist->id,
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get(route('payouts.voucher', $batch->id))
        ->assertSuccessful()
        ->assertSee(__('Liquidate again'))
        ->assertSee(route('payouts.create', ['instrumentist_id' => $instrumentist->id]), false);
});

test('hides liquidate again button when instrumentist has no pending procedures', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $instrumentist = User::factory()->create();
    $instrumentist->assignRole('instrumentist');

    $batch = makePaidBatchWithItem($instrumentist, $admin);

    $this->actingAs($admin)
        ->get(route('payouts.voucher', $batch->id))
        ->assertSuccessful()
        ->assertDontSee(__('Liquidate again'));
});
