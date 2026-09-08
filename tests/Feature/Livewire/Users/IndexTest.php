<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('users index shows view/edit links and a delete action for each active user', function () {
    $admin = User::factory()->create(['is_super_admin' => true, 'role' => 'admin']);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['role' => 'admin']);
    $staff->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee(route('users.show', $staff), false)
        ->assertSee(route('users.edit', $staff), false)
        ->assertSee('deleteUser('.$staff->id.')', false);
});

test('users index links do not expose the numeric user id in the url', function () {
    $admin = User::factory()->create(['is_super_admin' => true, 'role' => 'admin']);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['role' => 'admin']);
    $staff->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertDontSee('/users/'.$staff->id.'/edit', false)
        ->assertDontSee('/users/'.$staff->id.'"', false);
});
