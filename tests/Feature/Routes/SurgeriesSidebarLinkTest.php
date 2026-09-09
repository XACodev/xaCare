<?php

use App\Models\Hospital;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'surgeries.view', 'guard_name' => 'web']);
});

test('hospital admin with surgeries.view sees a link to the surgery schedule board', function () {
    $hospital = Hospital::factory()->create(['features' => ['qxlog']]);

    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->assignRole('admin');
    $admin->givePermissionTo('surgeries.view');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('surgeries.board'), false);
});

test('hospital admin without surgeries.view does not see the surgery schedule link', function () {
    $hospital = Hospital::factory()->create(['features' => ['qxlog']]);

    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('surgeries.board'), false);
});
