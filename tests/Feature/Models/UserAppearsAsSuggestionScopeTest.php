<?php
// tests/Feature/Models/UserAppearsAsSuggestionScopeTest.php

use App\Auth\PermissionTeamResolver;
use App\Models\Hospital;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'search.appear_as_suggestion', 'guard_name' => 'web']);
});

test('el scope solo incluye usuarios con el permiso, directo o via rol, en el team actual', function () {
    $hospital = Hospital::factory()->create();
    app(PermissionTeamResolver::class)->setPermissionsTeamId($hospital->id);

    $role = Role::firstOrCreate(['name' => 'instrumentist', 'guard_name' => 'web', 'team_id' => $hospital->id]);
    $role->givePermissionTo('search.appear_as_suggestion');

    $viaRole = User::factory()->create(['hospital_id' => $hospital->id]);
    $viaRole->assignRole($role);

    $viaDirectPermission = User::factory()->create(['hospital_id' => $hospital->id]);
    $viaDirectPermission->givePermissionTo('search.appear_as_suggestion');

    // 'admin' es un rol global (team_id null) que CoreRoleProvisioner nunca toca, así
    // que no recibe 'search.appear_as_suggestion' por defecto (a diferencia de los
    // roles core doctor/instrumentist/circulating, que sí lo reciben desde la Task 1).
    $withoutPermission = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);

    $ids = User::query()->where('hospital_id', $hospital->id)->appearsAsSuggestion()->pluck('id')->all();

    expect($ids)->toContain($viaRole->id, $viaDirectPermission->id);
    expect($ids)->not->toContain($withoutPermission->id);
});
