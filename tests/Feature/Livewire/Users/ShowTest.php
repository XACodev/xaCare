<?php

use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
});

test('hospital admin can view a staff profile from their own hospital', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'doctor', 'phone' => '55550001']);
    $staff->assignRole('doctor');

    $this->actingAs($admin)
        ->get(route('users.show', $staff))
        ->assertOk()
        ->assertSee($staff->name)
        ->assertSee($staff->username)
        ->assertSee($staff->phone);
});

test('the profile url is not the numeric id', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'doctor']);
    $staff->assignRole('doctor');

    expect(route('users.show', $staff))->not->toContain('/users/'.$staff->id);

    $this->actingAs($admin)
        ->get('/users/'.$staff->id)
        ->assertNotFound();
});

test('hospital admin can view the profile of a soft-deleted staff member', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'doctor']);
    $staff->assignRole('doctor');
    $staff->delete();

    $this->actingAs($admin)
        ->get(route('users.show', $staff))
        ->assertOk()
        ->assertSee($staff->name);
});

test('hospital admin cannot view a staff profile from another hospital', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['hospital_id' => $otherHospital->id]);

    $this->actingAs($admin)
        ->get(route('users.show', $staff))
        ->assertNotFound();
});

test('non admin cannot view a staff profile', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'instrumentist']);
    $staff = User::factory()->create(['hospital_id' => $hospital->id]);

    $this->actingAs($user)
        ->get(route('users.show', $staff))
        ->assertForbidden();
});
