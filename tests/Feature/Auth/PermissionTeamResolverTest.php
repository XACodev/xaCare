<?php

use App\Auth\PermissionTeamResolver;
use App\Models\Hospital;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    PermissionTeamResolver::clearExplicitTeamId();
});

test('guest users have no permissions team', function () {
    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();
});

test('a hospital user resolves the team to their hospital', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'is_platform_admin' => false,
    ]);

    $this->actingAs($user);

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($hospital->id);
});

test('a platform admin resolves the team to null', function () {
    $user = User::factory()->create([
        'is_platform_admin' => true,
        'hospital_id' => null,
    ]);

    $this->actingAs($user);

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();
});

test('an explicit team id wins over the authenticated hospital user', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'is_platform_admin' => false,
    ]);

    $this->actingAs($user);
    setPermissionsTeamId($otherHospital->id);

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($otherHospital->id);
});

test('clearing the explicit team id falls back to the authenticated hospital', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'is_platform_admin' => false,
    ]);

    $this->actingAs($user);
    setPermissionsTeamId(999);
    PermissionTeamResolver::clearExplicitTeamId();

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($hospital->id);
});

test('a hospital user without hospital_id resolves the team to null', function () {
    $user = User::withoutEvents(fn () => User::factory()->create([
        'is_platform_admin' => false,
        'hospital_id' => null,
        'role' => 'admin',
    ]));

    $this->actingAs($user);

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();
});
