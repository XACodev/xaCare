<?php

use App\Models\Hospital;
use App\Models\SurgicalAssignment;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'instrumentist', 'guard_name' => 'web']);

    Permission::create(['name' => 'payouts.create', 'guard_name' => 'web']);
    $adminRole->givePermissionTo('payouts.create');
});

test('shows pending and selected procedure counts', function () {
    $hospital = Hospital::factory()->create();

    $admin = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin->assignRole('admin');

    $instrumentist = User::factory()->create(['hospital_id' => $hospital->id]);
    $instrumentist->assignRole('instrumentist');

    $this->actingAs($admin);

    $assignments = SurgicalAssignment::factory()->count(3)->create([
        'user_id' => $instrumentist->id,
        'status' => 'pending',
        'calculated_amount' => 100,
    ]);

    Volt::test('payouts.create')
        ->set('payee_id', $instrumentist->id)
        ->assertSee(__(':count procedures', ['count' => 3]))
        ->set('selected', [$assignments->first()->id])
        ->assertSee(__(':count selected', ['count' => 1]));
});

test('liquidating redirects to the payout voucher', function () {
    $hospital = Hospital::factory()->create();

    $admin = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin->assignRole('admin');

    $instrumentist = User::factory()->create(['hospital_id' => $hospital->id]);
    $instrumentist->assignRole('instrumentist');

    $this->actingAs($admin);

    $assignments = SurgicalAssignment::factory()->count(2)->create([
        'user_id' => $instrumentist->id,
        'status' => 'pending',
        'calculated_amount' => 150,
    ]);

    $component = Volt::test('payouts.create')
        ->set('payee_id', $instrumentist->id)
        ->set('selected', $assignments->pluck('id')->all())
        ->call('liquidate');

    $batch = \App\Models\PayoutBatch::firstOrFail();

    $component->assertRedirect(route('payouts.voucher', $batch->id));

    expect($assignments->first()->fresh()->status)->toBe('paid');
});

test('preselects instrumentist from query parameter', function () {
    $hospital = Hospital::factory()->create();

    $admin = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin->assignRole('admin');

    $instrumentist = User::factory()->create(['hospital_id' => $hospital->id]);
    $instrumentist->assignRole('instrumentist');

    $this->actingAs($admin);

    SurgicalAssignment::factory()->create([
        'user_id' => $instrumentist->id,
        'status' => 'pending',
        'calculated_amount' => 100,
    ]);

    $this->get(route('payouts.create', ['payee_id' => $instrumentist->id]))
        ->assertSuccessful()
        ->assertSee(__(':count procedures', ['count' => 1]));
});
