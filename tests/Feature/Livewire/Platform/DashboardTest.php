<?php

use App\Enums\SubscriptionStatus;
use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;

test('dashboard lists hospitals whose trial ends within 7 days', function () {
    $soon = Hospital::factory()->create([
        'subscription_status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->addDays(3),
    ]);
    Hospital::factory()->create([
        'subscription_status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->addDays(20),
    ]);

    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $this->actingAs($admin)
        ->get(route('platform.dashboard'))
        ->assertSee($soon->name);
});

test('dashboard shows recent activity entries', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $causer = User::factory()->create(['hospital_id' => $hospital->id]);

    activity()->causedBy($causer)->performedOn($hospital)->log('updated');

    Volt::actingAs($admin)->test('platform.dashboard')
        ->assertSee('updated');
});

test('dashboard shows hospital counts by subscription status and plan', function () {
    Hospital::factory()->create(['plan' => 'basic', 'subscription_status' => SubscriptionStatus::Active]);
    Hospital::factory()->create(['plan' => 'basic', 'subscription_status' => SubscriptionStatus::Trialing]);
    Hospital::factory()->create(['plan' => 'pro', 'subscription_status' => SubscriptionStatus::Active]);
    Hospital::factory()->create(['plan' => 'pro', 'subscription_status' => SubscriptionStatus::Canceled]);

    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $component = Volt::actingAs($admin)->test('platform.dashboard');

    expect($component->get('hospitalStats'))->toMatchArray([
        'total' => 4,
        'active' => 2,
        'trialing' => 1,
        'past_due_or_canceled' => 1,
    ]);
    expect($component->get('hospitalStats')['by_plan']->toArray())->toBe(['basic' => 2, 'pro' => 2]);
});

test('dashboard shows total platform users excluding platform admins themselves', function () {
    $hospital = Hospital::factory()->create();
    User::factory()->count(3)->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false]);
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $component = Volt::actingAs($admin)->test('platform.dashboard');

    expect($component->get('totalPlatformUsers'))->toBe(3);
});
