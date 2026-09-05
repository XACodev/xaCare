<?php

use App\Enums\SubscriptionStatus;
use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;

test('creating a hospital starts a trial and syncs plan features', function () {
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $this->actingAs($superAdmin);

    Volt::test('platform.hospitals.create')
        ->set('name', 'Hospital Con Trial')
        ->set('plan', 'pro')
        ->call('save')
        ->assertHasNoErrors();

    $hospital = Hospital::where('name', 'Hospital Con Trial')->first();

    expect($hospital)->not->toBeNull()
        ->and($hospital->plan)->toBe('pro')
        ->and($hospital->subscription_status)->toBe(SubscriptionStatus::Trialing)
        ->and($hospital->trial_ends_at?->isFuture())->toBeTrue()
        ->and($hospital->hasFeature('insurance'))->toBeTrue();
});

test('super admin can change plan and subscription status', function () {
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $hospital = Hospital::factory()->create(['plan' => 'basic']);
    $this->actingAs($superAdmin);

    Volt::test('platform.hospitals.edit', ['hospital' => $hospital->id])
        ->set('plan', 'pro')
        ->set('subscription_status', SubscriptionStatus::Active->value)
        ->set('trial_ends_at', '')
        ->call('save')
        ->assertHasNoErrors();

    $hospital->refresh();

    expect($hospital->plan)->toBe('pro')
        ->and($hospital->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and($hospital->hasFeature('insurance'))->toBeTrue()
        ->and($hospital->hasFeature('patients'))->toBeTrue();
});

test('pilot hospitals cannot be left in trialing status', function () {
    config(['billing.pilot_hospital_slugs' => ['hnsc']]);

    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $hospital = Hospital::factory()->create(['slug' => 'hnsc', 'subscription_status' => SubscriptionStatus::Active]);
    $this->actingAs($superAdmin);

    Volt::test('platform.hospitals.edit', ['hospital' => $hospital->id])
        ->set('subscription_status', SubscriptionStatus::Trialing->value)
        ->call('save')
        ->assertHasErrors('subscription_status');

    expect($hospital->refresh()->subscription_status)->toBe(SubscriptionStatus::Active);
});

test('canceling a subscription deactivates the hospital', function () {
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $hospital = Hospital::factory()->create(['subscription_status' => SubscriptionStatus::Active, 'is_active' => true]);
    $this->actingAs($superAdmin);

    Volt::test('platform.hospitals.edit', ['hospital' => $hospital->id])
        ->set('subscription_status', SubscriptionStatus::Canceled->value)
        ->set('trial_ends_at', '')
        ->call('save')
        ->assertHasNoErrors();

    $hospital->refresh();

    expect($hospital->subscription_status)->toBe(SubscriptionStatus::Canceled)
        ->and($hospital->is_active)->toBeFalse();
});

test('changing subscription status is recorded in the activity log', function () {
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $hospital = Hospital::factory()->create(['plan' => 'basic', 'subscription_status' => SubscriptionStatus::Active]);
    $this->actingAs($superAdmin);

    Volt::test('platform.hospitals.edit', ['hospital' => $hospital->id])
        ->set('plan', 'pro')
        ->set('subscription_status', SubscriptionStatus::PastDue->value)
        ->set('trial_ends_at', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(\Spatie\Activitylog\Models\Activity::query()->where('subject_id', $hospital->id)->where('subject_type', Hospital::class)->exists())->toBeTrue();
});
