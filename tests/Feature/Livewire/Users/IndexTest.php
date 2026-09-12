<?php

use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;
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

test('staff index groups users by their actual role instead of "unknown"', function () {
    $hospital = Hospital::factory()->create();
    Role::firstOrCreate(['name' => 'instrumentist', 'guard_name' => 'web']);

    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    $instrumentist = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'instrumentist']);
    $instrumentist->assignRole('instrumentist');

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertDontSee('Unknown')
        ->assertSeeInOrder(['Instrumentist', $instrumentist->name], false);
});

test('role filter only shows roles from current hospital', function () {
    $hospitalA = Hospital::factory()->create(['enabled_roles' => ['cirujano']]);
    $hospitalB = Hospital::factory()->create(['enabled_roles' => ['cirujano']]);

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'cirujano', 'guard_name' => 'web', 'team_id' => $hospitalA->id]);
    Role::firstOrCreate(['name' => 'cirujano', 'guard_name' => 'web', 'team_id' => $hospitalB->id]);
    Role::firstOrCreate(['name' => 'enfermera', 'guard_name' => 'web', 'team_id' => $hospitalB->id]);

    $admin = User::factory()->create(['hospital_id' => $hospitalA->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($admin);

    $roles = Volt::test('users.index')->get('rolesAvailable');
    $roleNames = array_values($roles);

    expect($roleNames)->toContain('admin')
        ->toContain('cirujano')
        ->not->toContain('enfermera')
        ->and(array_count_values($roleNames)['cirujano'] ?? 0)->toBe(1);
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
