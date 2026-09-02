<?php

use App\Models\SurgicalCase;
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
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $instrumentist = User::factory()->create();
    $instrumentist->assignRole('instrumentist');

    $procedures = SurgicalCase::factory()->count(3)->create([
        'instrumentist_id' => $instrumentist->id,
        'status' => 'pending',
        'calculated_amount' => 100,
    ]);

    $this->actingAs($admin);

    Volt::test('payouts.create')
        ->set('instrumentist_id', $instrumentist->id)
        ->assertSee(__(':count procedures', ['count' => 3]))
        ->set('selected', [$procedures->first()->id])
        ->assertSee(__(':count selected', ['count' => 1]));
});

test('liquidating redirects to the payout voucher', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $instrumentist = User::factory()->create();
    $instrumentist->assignRole('instrumentist');

    $procedures = SurgicalCase::factory()->count(2)->create([
        'instrumentist_id' => $instrumentist->id,
        'status' => 'pending',
        'calculated_amount' => 150,
    ]);

    $this->actingAs($admin);

    $component = Volt::test('payouts.create')
        ->set('instrumentist_id', $instrumentist->id)
        ->set('selected', $procedures->pluck('id')->all())
        ->call('liquidate');

    $batch = \App\Models\PayoutBatch::firstOrFail();

    $component->assertRedirect(route('payouts.voucher', $batch->id));

    expect($procedures->first()->fresh()->status)->toBe('paid');
});

test('preselects instrumentist from query parameter', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $instrumentist = User::factory()->create();
    $instrumentist->assignRole('instrumentist');

    SurgicalCase::factory()->create([
        'instrumentist_id' => $instrumentist->id,
        'status' => 'pending',
        'calculated_amount' => 100,
    ]);

    $this->actingAs($admin);

    $this->get(route('payouts.create', ['instrumentist_id' => $instrumentist->id]))
        ->assertSuccessful()
        ->assertSee(__(':count procedures', ['count' => 1]));
});
