<?php

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function loadSplitCoreRolesMigration(): object
{
    return include database_path('migrations/2026_09_10_010000_split_core_roles_per_hospital.php');
}

/**
 * Simula el estado pre-Fase-B: borra los roles por-hospital que
 * CoreRoleProvisioner ya creo automaticamente y crea el rol global
 * legacy, para poder ejercitar la migracion de backfill.
 */
function resetToLegacyGlobalRole(string $roleName, array $permissionNames = []): Role
{
    Role::where('name', $roleName)->where('guard_name', 'web')->whereNotNull('team_id')->delete();

    $globalRole = Role::create(['name' => $roleName, 'guard_name' => 'web', 'team_id' => null]);

    if ($permissionNames) {
        foreach ($permissionNames as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }
        $globalRole->syncPermissions($permissionNames);
    }

    return $globalRole;
}

test('migration copies the global role permissions to a per-hospital role and reassigns user pivots', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'doctor']);

    $globalRole = resetToLegacyGlobalRole('doctor', ['procedures.create', 'procedures.view']);

    // resetToLegacyGlobalRole borra el rol por-hospital y, por cascada, la fila
    // de model_has_roles que lo apuntaba: hay que re-insertarla contra el rol
    // global legacy para simular el estado pre-Fase-B.
    DB::table('model_has_roles')->insert([
        'role_id' => $globalRole->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->id,
        'team_id' => $hospital->id,
    ]);

    loadSplitCoreRolesMigration()->up();

    $hospitalRole = Role::where('name', 'doctor')->where('guard_name', 'web')->where('team_id', $hospital->id)->first();

    expect($hospitalRole)->not->toBeNull()
        ->and($hospitalRole->permissions()->pluck('name')->sort()->values()->all())->toBe(['procedures.create', 'procedures.view'])
        ->and(Role::where('name', 'doctor')->where('guard_name', 'web')->whereNull('team_id')->exists())->toBeFalse()
        ->and(DB::table('model_has_roles')->where('model_id', $user->id)->value('role_id'))->toBe($hospitalRole->id);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('migration splits the global role per hospital when several hospitals have users on it', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $globalRole = resetToLegacyGlobalRole('instrumentist');

    loadSplitCoreRolesMigration()->up();

    $roleA = Role::where('name', 'instrumentist')->where('guard_name', 'web')->where('team_id', $hospitalA->id)->first();
    $roleB = Role::where('name', 'instrumentist')->where('guard_name', 'web')->where('team_id', $hospitalB->id)->first();

    expect($roleA)->not->toBeNull()
        ->and($roleB)->not->toBeNull()
        ->and($roleA->id)->not->toBe($roleB->id)
        ->and(Role::where('id', $globalRole->id)->exists())->toBeFalse();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('migration is a no-op when no global core role remains', function () {
    Hospital::factory()->create();

    expect(fn () => loadSplitCoreRolesMigration()->up())->not->toThrow(Throwable::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});
