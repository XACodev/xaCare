<?php

use App\Enums\SubscriptionStatus;
use App\Models\Hospital;
use App\Services\HospitalPlanService;

test('applying a plan copies features from the billing catalog', function () {
    $hospital = Hospital::factory()->create(['plan' => 'basic', 'features' => []]);

    $updated = app(HospitalPlanService::class)->applyPlan($hospital, 'pro');

    expect($updated->plan)->toBe('pro')
        ->and($updated->hasFeature('insurance'))->toBeTrue()
        ->and($updated->hasFeature('qxlog'))->toBeTrue()
        ->and($updated->hasFeature('ehr'))->toBeFalse();
});

test('unknown plans are rejected', function () {
    $hospital = Hospital::factory()->create();

    app(HospitalPlanService::class)->applyPlan($hospital, 'enterprise');
})->throws(InvalidArgumentException::class);

test('a new trial allows access until it expires', function () {
    $hospital = Hospital::factory()->create([
        'subscription_status' => SubscriptionStatus::Canceled,
        'is_active' => true,
    ]);

    $updated = app(HospitalPlanService::class)->startTrial($hospital, 'basic');

    expect($updated->subscription_status)->toBe(SubscriptionStatus::Trialing)
        ->and($updated->trial_ends_at?->isFuture())->toBeTrue()
        ->and($updated->subscriptionAllowsAccess())->toBeTrue();
});

test('an expired trial does not allow access', function () {
    $hospital = Hospital::factory()->create([
        'subscription_status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->subMinute(),
        'is_active' => true,
    ]);

    expect($hospital->subscriptionAllowsAccess())->toBeFalse();
});

test('canceled and inactive hospitals do not allow access', function () {
    $canceled = Hospital::factory()->create([
        'subscription_status' => SubscriptionStatus::Canceled,
        'is_active' => true,
    ]);
    $inactive = Hospital::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
        'is_active' => false,
    ]);

    expect($canceled->subscriptionAllowsAccess())->toBeFalse()
        ->and($inactive->subscriptionAllowsAccess())->toBeFalse();
});
