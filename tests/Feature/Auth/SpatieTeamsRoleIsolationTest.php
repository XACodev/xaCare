<?php

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (Hospital::CORE_ROLES as $roleName) {
        Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
            'team_id' => null,
        ]);
    }
});

test('a custom role created for hospital A is not found in hospital B', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    Role::create([
        'name' => 'bodeguero',
        'guard_name' => 'web',
        'team_id' => $hospitalA->id,
    ]);

    $userA = User::factory()->create([
        'hospital_id' => $hospitalA->id,
        'role' => 'admin',
    ]);
    $userB = User::factory()->create([
        'hospital_id' => $hospitalB->id,
        'role' => 'admin',
    ]);

    $this->actingAs($userA);
    expect(Role::findByName('bodeguero')->team_id)->toBe($hospitalA->id);

    $this->actingAs($userB);
    expect(fn () => Role::findByName('bodeguero'))->toThrow(RoleDoesNotExist::class);
});

test('a user in hospital A can have a custom role that hospital B cannot assign', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $customRole = Role::create([
        'name' => 'contador',
        'guard_name' => 'web',
        'team_id' => $hospitalA->id,
    ]);

    $userA = User::factory()->create([
        'hospital_id' => $hospitalA->id,
        'role' => 'admin',
    ]);
    $userB = User::factory()->create([
        'hospital_id' => $hospitalB->id,
        'role' => 'admin',
    ]);

    $userA->assignRole($customRole);

    $this->actingAs($userA);
    $userA->unsetRelation('roles')->unsetRelation('permissions');
    expect($userA->hasRole('contador'))->toBeTrue();

    $this->actingAs($userB);
    $userB->unsetRelation('roles')->unsetRelation('permissions');
    expect($userB->hasRole('contador'))->toBeFalse();
    expect(fn () => $userB->assignRole('contador'))->toThrow(RoleDoesNotExist::class);
});

test('core admin role works for users of any hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $adminA = User::factory()->create([
        'hospital_id' => $hospitalA->id,
        'role' => 'admin',
    ]);
    $adminB = User::factory()->create([
        'hospital_id' => $hospitalB->id,
        'role' => 'admin',
    ]);

    $this->actingAs($adminA);
    $adminA->unsetRelation('roles')->unsetRelation('permissions');
    expect($adminA->hasRole('admin'))->toBeTrue();

    $this->actingAs($adminB);
    $adminB->unsetRelation('roles')->unsetRelation('permissions');
    expect($adminB->hasRole('admin'))->toBeTrue();

    expect(Role::where('name', 'admin')->whereNull('team_id')->count())->toBe(1);
});

test('visible role names include core, enabled global extras, and hospital custom roles', function () {
    $hospitalA = Hospital::factory()->create(['enabled_roles' => ['anesthesiologist']]);
    $hospitalB = Hospital::factory()->create(['enabled_roles' => []]);

    Role::create([
        'name' => 'anesthesiologist',
        'guard_name' => 'web',
        'team_id' => null,
    ]);
    Role::create([
        'name' => 'bodeguero',
        'guard_name' => 'web',
        'team_id' => $hospitalA->id,
    ]);
    Role::create([
        'name' => 'contador',
        'guard_name' => 'web',
        'team_id' => $hospitalB->id,
    ]);

    expect($hospitalA->visibleRoleNames())->toEqualCanonicalizing([
        'admin',
        'doctor',
        'instrumentist',
        'circulating',
        'anesthesiologist',
        'bodeguero',
    ]);

    expect($hospitalB->visibleRoleNames())->toEqualCanonicalizing([
        'admin',
        'doctor',
        'instrumentist',
        'circulating',
        'contador',
    ]);
});

test('givePermissionTo stamps the pivot with the user hospital without an authenticated actor', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'doctor',
    ]);
    Permission::findOrCreate('procedures.view', 'web');

    $user->givePermissionTo('procedures.view');

    expect(DB::table('model_has_permissions')->where('model_id', $user->id)->value('team_id'))
        ->toBe($hospital->id);

    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect($user->can('procedures.view'))->toBeTrue();
});

test('a hospital user keeps direct permissions when a platform admin is authenticated', function () {
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

    Permission::findOrCreate('procedures.view', 'web');
    $user->givePermissionTo('procedures.view');
    $this->actingAs($platformAdmin);
    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect($user->can('procedures.view'))->toBeTrue();
});

test('a user cannot keep a custom role from another hospital after switching team context', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $customRole = Role::create([
        'name' => 'mantenimiento',
        'guard_name' => 'web',
        'team_id' => $hospitalA->id,
    ]);

    $user = User::factory()->create([
        'hospital_id' => $hospitalA->id,
        'role' => 'admin',
    ]);
    $user->assignRole($customRole);

    $this->actingAs($user);
    $user->unsetRelation('roles')->unsetRelation('permissions');
    expect($user->hasRole('mantenimiento'))->toBeTrue();

    setPermissionsTeamId($hospitalB->id);
    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect($user->hasRole('mantenimiento'))->toBeFalse();
});
