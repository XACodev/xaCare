<?php

use App\Enums\SubscriptionStatus;
use App\Models\Hospital;
use App\Models\User;

test('a user whose hospital subscription does not allow access is redirected to the suspended view', function () {
    $hospital = Hospital::factory()->create([
        'subscription_status' => SubscriptionStatus::Canceled,
        'is_active' => false,
    ]);
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('billing.suspended'));
});

test('json requests from a suspended hospital still receive a 403', function () {
    $hospital = Hospital::factory()->create([
        'subscription_status' => SubscriptionStatus::Canceled,
        'is_active' => false,
    ]);
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false]);

    $this->actingAs($user)
        ->getJson(route('dashboard'))
        ->assertStatus(403);
});

test('the suspended view renders for an authenticated user', function () {
    $hospital = Hospital::factory()->create([
        'subscription_status' => SubscriptionStatus::Canceled,
        'is_active' => false,
    ]);
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false]);

    $this->actingAs($user)
        ->get(route('billing.suspended'))
        ->assertOk()
        ->assertSee('Suscripción suspendida');
});
