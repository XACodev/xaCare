<?php

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function loadEnableSpatieTeamsMigration(): object
{
    return include database_path('migrations/2026_09_04_155454_enable_spatie_teams.php');
}

test('permission tables have a nullable team_id after migrating', function () {
    expect(Schema::hasColumn('roles', 'team_id'))->toBeTrue()
        ->and(Schema::hasColumn('model_has_roles', 'team_id'))->toBeTrue()
        ->and(Schema::hasColumn('model_has_permissions', 'team_id'))->toBeTrue();

    $rolesTeamId = collect(Schema::getColumns('roles'))->firstWhere('name', 'team_id');
    $pivotTeamId = collect(Schema::getColumns('model_has_roles'))->firstWhere('name', 'team_id');
    $directTeamId = collect(Schema::getColumns('model_has_permissions'))->firstWhere('name', 'team_id');

    expect($rolesTeamId['nullable'])->toBeTrue()
        ->and($pivotTeamId['nullable'])->toBeTrue()
        ->and($directTeamId['nullable'])->toBeTrue();
});

test('adding team columns is idempotent when they already exist', function () {
    $migration = loadEnableSpatieTeamsMigration();

    expect(fn () => $migration->addTeamIdColumns())->not->toThrow(Throwable::class);
    expect(Schema::hasColumn('roles', 'team_id'))->toBeTrue();
});

test('backfill keeps core roles global and stamps assignments with the user hospital', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'doctor',
    ]);

    DB::table('roles')->where('name', 'doctor')->update(['team_id' => $hospital->id]);
    DB::table('model_has_roles')
        ->where('model_id', $user->id)
        ->update(['team_id' => null]);

    loadEnableSpatieTeamsMigration()->backfillTeamIds();

    expect(DB::table('roles')->where('name', 'doctor')->value('team_id'))->toBeNull()
        ->and(DB::table('model_has_roles')->where('model_id', $user->id)->value('team_id'))
        ->toBe($hospital->id);
});

test('backfill does not overwrite assignments that already have a team', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'admin',
    ]);

    DB::table('model_has_roles')
        ->where('model_id', $user->id)
        ->update(['team_id' => $otherHospital->id]);

    loadEnableSpatieTeamsMigration()->backfillTeamIds();

    expect(DB::table('model_has_roles')->where('model_id', $user->id)->value('team_id'))
        ->toBe($otherHospital->id);
});

test('backfill leaves platform admin assignments without a hospital as null', function () {
    $user = User::factory()->create([
        'is_platform_admin' => true,
        'hospital_id' => null,
        'role' => 'admin',
    ]);

    DB::table('model_has_roles')
        ->where('model_id', $user->id)
        ->update(['team_id' => null]);

    loadEnableSpatieTeamsMigration()->backfillTeamIds();

    expect(DB::table('model_has_roles')->where('model_id', $user->id)->value('team_id'))
        ->toBeNull();
});

test('backfill maps a custom role to the single hospital that enabled it', function () {
    $hospital = Hospital::factory()->create(['enabled_roles' => ['anesthesiologist']]);
    Hospital::factory()->create(['enabled_roles' => []]);

    Role::create(['name' => 'anesthesiologist', 'guard_name' => 'web', 'team_id' => null]);

    loadEnableSpatieTeamsMigration()->backfillTeamIds();

    expect(DB::table('roles')->where('name', 'anesthesiologist')->value('team_id'))
        ->toBe($hospital->id);
});

test('backfill keeps a custom role global when two hospitals enabled it', function () {
    Hospital::factory()->create(['enabled_roles' => ['anesthesiologist']]);
    Hospital::factory()->create(['enabled_roles' => ['anesthesiologist']]);

    Role::create(['name' => 'anesthesiologist', 'guard_name' => 'web', 'team_id' => null]);

    loadEnableSpatieTeamsMigration()->backfillTeamIds();

    expect(DB::table('roles')->where('name', 'anesthesiologist')->value('team_id'))
        ->toBeNull();
});

test('backfill stamps direct permission assignments with the user hospital', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'doctor',
    ]);
    $permission = Permission::create(['name' => 'procedures.view', 'guard_name' => 'web']);

    DB::table('model_has_permissions')->insert([
        'permission_id' => $permission->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->id,
        'team_id' => null,
    ]);

    loadEnableSpatieTeamsMigration()->backfillTeamIds();

    expect(DB::table('model_has_permissions')->where('model_id', $user->id)->value('team_id'))
        ->toBe($hospital->id);
});

test('backfill can run twice without changing already migrated data', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'circulating',
    ]);

    $migration = loadEnableSpatieTeamsMigration();
    $migration->backfillTeamIds();
    $migration->backfillTeamIds();

    expect(DB::table('roles')->where('name', 'circulating')->value('team_id'))->toBeNull()
        ->and(DB::table('model_has_roles')->where('model_id', $user->id)->value('team_id'))
        ->toBe($hospital->id);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});
