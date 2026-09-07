<?php

use App\Models\Hospital;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('staff index shows view/edit links and a delete action for each active user', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $staff->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee(route('users.show', $staff), false)
        ->assertSee(route('users.edit', $staff), false)
        ->assertSee('deleteUser('.$staff->id.')', false);
});

test('staff index links do not expose the numeric user id in the url', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $staff->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertDontSee('/users/'.$staff->id.'/edit', false)
        ->assertDontSee('/users/'.$staff->id.'"', false);
});
