<?php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\PayoutBatch;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'payouts.view', 'guard_name' => 'web']);
    $adminRole->givePermissionTo('payouts.view');
});

function payoutsIndexAdmin(Hospital $hospital): User
{
    $admin = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin->assignRole('admin');

    return $admin;
}

test('muestra el estado traducido y la fecha formateada', function () {
    $hospital = Hospital::factory()->create();
    $admin = payoutsIndexAdmin($hospital);
    $payee = User::factory()->create(['hospital_id' => $hospital->id]);

    $this->actingAs($admin);

    PayoutBatch::factory()->create([
        'hospital_id' => $hospital->id,
        'payee_id' => $payee->id,
        'paid_by_id' => $admin->id,
        'paid_at' => '2026-09-08 07:47:55',
        'status' => 'active',
    ]);

    Volt::test('qxlog.payouts.index')
        ->set('instrumentist_id', (string) $payee->id)
        ->assertSee('08/09/2026')
        ->assertSee(__('Active'))
        ->assertDontSee('2026-09-08 07:47:55');
});

test('muestra un lote anulado como Void traducido', function () {
    $hospital = Hospital::factory()->create();
    $admin = payoutsIndexAdmin($hospital);
    $payee = User::factory()->create(['hospital_id' => $hospital->id]);

    $this->actingAs($admin);

    PayoutBatch::factory()->create([
        'hospital_id' => $hospital->id,
        'payee_id' => $payee->id,
        'paid_by_id' => $admin->id,
        'status' => 'void',
    ]);

    Volt::test('qxlog.payouts.index')
        ->set('instrumentist_id', (string) $payee->id)
        ->assertSee(__('Void'));
});
