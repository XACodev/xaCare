<?php

use Livewire\Volt\Volt;
use App\Models\User;
use App\Models\SurgicalAssignment;

test('earnings are hidden by default and toggle works', function () {
    $hospital = \App\Models\Hospital::factory()->create();
    $user = User::factory()->create(['role' => 'instrumentist', 'hospital_id' => $hospital->id]);
    $this->actingAs($user);

    // Create some data so we can verify the amount is actually hidden/shown
    SurgicalAssignment::factory()->create([
        'hospital_id' => $hospital->id,
        'user_id' => $user->id,
        'status' => 'paid',
        'calculated_amount' => 500,
    ]);

    Volt::test('dashboard')
        ->assertSet('showEarnings', false)
        ->assertSee('blur-md') // Should see blur class initially
        ->call('toggleEarnings')
        ->assertSet('showEarnings', true)
        ->assertDontSee('blur-md'); // Should NOT see blur class after toggle
});

test('admins do not see blur class', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user);

    Volt::test('dashboard')
        ->assertSet('showEarnings', false) // It exists but isn't used
        ->assertDontSee('blur-md');
});
