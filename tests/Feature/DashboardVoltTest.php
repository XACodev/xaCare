<?php

use App\Models\User;
use App\Modules\QxLog\Models\SurgicalAssignment;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;

afterEach(function () {
    Carbon::setTestNow();
});

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

test('greets buenos dias before noon', function () {
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-14 09:15:00'));

    $user = User::factory()->create(['role' => 'admin', 'name' => 'Ana Lopez']);
    $this->actingAs($user);

    Volt::test('dashboard')
        ->assertSee('Buenos días, Ana');
});

test('greets buenas noches from 19 onward', function () {
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-14 21:00:00'));

    $user = User::factory()->create(['role' => 'admin', 'name' => 'Ana Lopez']);
    $this->actingAs($user);

    Volt::test('dashboard')
        ->assertSee('Buenas noches, Ana');
});

test('shows shift label next to the date when set', function () {
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-09-14 15:00:00'));

    $user = User::factory()->create([
        'role' => 'admin',
        'name' => 'Ana Lopez',
        'shift_label' => 'Turno mañana',
    ]);
    $this->actingAs($user);

    Volt::test('dashboard')
        ->assertSee('Buenas tardes, Ana')
        ->assertSee('Turno mañana');
});
