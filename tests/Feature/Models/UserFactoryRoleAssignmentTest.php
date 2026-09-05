<?php

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

test('user factory assigns a core spatie role with a hospital team on the pivot', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'doctor',
    ]);

    $role = Role::where('name', 'doctor')->where('guard_name', 'web')->first();

    expect($role)->not->toBeNull()
        ->and($role->team_id)->toBeNull()
        ->and(DB::table('model_has_roles')->where('model_id', $user->id)->value('team_id'))
        ->toBe($hospital->id);

    $this->actingAs($user);
    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect($user->hasRole('doctor'))->toBeTrue()
        ->and($user->hasRole('admin'))->toBeFalse();
});

test('user factory assigns platform admin roles without a hospital team', function () {
    $user = User::factory()->create([
        'is_platform_admin' => true,
        'hospital_id' => null,
        'role' => 'admin',
    ]);

    expect(DB::table('model_has_roles')->where('model_id', $user->id)->value('team_id'))
        ->toBeNull();

    $this->actingAs($user);
    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect($user->hasRole('admin'))->toBeTrue();
});

test('user factory skips spatie assignment when the role string is empty', function () {
    $user = User::factory()->create(['role' => '']);

    expect(DB::table('model_has_roles')->where('model_id', $user->id)->exists())->toBeFalse();
});

test('getRoleNames sees hospital-scoped roles even when a platform admin is authenticated', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'doctor',
    ]);
    $platformAdmin = User::factory()->create([
        'is_platform_admin' => true,
        'hospital_id' => null,
        'role' => 'admin',
    ]);

    $this->actingAs($platformAdmin);

    expect($user->getRoleNames()->all())->toContain('doctor');
});

test('user factory creates a custom role scoped to the user hospital', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'bodeguero',
    ]);

    $role = Role::where('name', 'bodeguero')->where('guard_name', 'web')->first();

    expect($role)->not->toBeNull()
        ->and($role->team_id)->toBe($hospital->id)
        ->and(DB::table('model_has_roles')->where('model_id', $user->id)->value('team_id'))
        ->toBe($hospital->id);

    $this->actingAs($user);
    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect($user->hasRole('bodeguero'))->toBeTrue();
});

test('assigning a role twice for the same hospital does not duplicate the pivot row', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'admin',
    ]);

    $user->assignRole('admin');

    expect(DB::table('model_has_roles')->where('model_id', $user->id)->count())->toBe(1);
});
