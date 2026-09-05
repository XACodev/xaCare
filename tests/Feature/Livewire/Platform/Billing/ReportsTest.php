<?php

use App\Enums\SubscriptionStatus;
use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;

test('non platform admins cannot view billing reports', function () {
    $user = User::factory()->create(['is_platform_admin' => false]);
    $this->actingAs($user);

    Volt::test('platform.billing.reports')->assertStatus(403);
});

test('reports show mrr, status counts and expiring trials', function () {
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $this->actingAs($superAdmin);

    Hospital::factory()->create(['plan' => 'basic', 'subscription_status' => SubscriptionStatus::Active]);
    Hospital::factory()->create(['plan' => 'pro', 'subscription_status' => SubscriptionStatus::Active]);
    Hospital::factory()->create([
        'subscription_status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->addDays(3),
    ]);

    $component = Volt::test('platform.billing.reports');

    $expectedMrr = config('billing.plans.basic.price') + config('billing.plans.pro.price');

    expect($component->get('mrr'))->toBe((float) $expectedMrr)
        ->and($component->get('byStatus')['active'])->toBe(2)
        ->and($component->get('byStatus')['trialing'])->toBe(1)
        ->and($component->get('expiringTrials')[7])->toBe(1);
});

test('reports can be filtered by status and plan', function () {
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $this->actingAs($superAdmin);

    Hospital::factory()->create(['name' => 'Activo Pro', 'plan' => 'pro', 'subscription_status' => SubscriptionStatus::Active]);
    Hospital::factory()->create(['name' => 'Trial Basic', 'plan' => 'basic', 'subscription_status' => SubscriptionStatus::Trialing]);

    $filtered = Volt::test('platform.billing.reports')
        ->set('status_filter', SubscriptionStatus::Trialing->value)
        ->get('hospitals');

    expect($filtered)->toHaveCount(1)
        ->and($filtered->first()->name)->toBe('Trial Basic');
});

test('billing report csv export streams a csv file', function () {
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $this->actingAs($superAdmin);

    Hospital::factory()->create(['name' => 'Exportable']);

    Volt::test('platform.billing.reports')
        ->call('exportCsv')
        ->assertFileDownloaded();
});
