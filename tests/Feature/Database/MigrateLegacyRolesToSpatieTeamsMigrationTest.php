<?php

use App\Models\Hospital;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * RefreshDatabase ya corre esta migración (sin usuarios legacy todavía) antes de cada test.
 * Para ejercitar su up() contra datos legacy reales, se vuelve a invocar explícitamente
 * después de sembrar usuarios con `role` legacy -- mismo patrón que
 * MigrateLegacyProceduresMigrationTest.php.
 */
function loadMigrateLegacyRolesMigration(): object
{
    return include database_path('migrations/2026_09_05_230000_migrate_legacy_roles_to_spatie_teams.php');
}

test('asigna el rol Spatie global a un usuario legacy con role admin', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);

    loadMigrateLegacyRolesMigration()->up();

    $user->unsetRelation('roles');
    expect($user->hasRole('admin'))->toBeTrue()
        ->and($user->can('pricing.manage'))->toBeTrue();

    $roleId = Role::where('name', 'admin')->whereNull('team_id')->value('id');
    expect(DB::table('model_has_roles')->where('model_id', $user->id)->where('role_id', $roleId)->value('team_id'))
        ->toBe($hospital->id);
});

test('un usuario instrumentist legacy puede acceder a vistas de instrumentista tras la migración', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'instrumentist']);

    loadMigrateLegacyRolesMigration()->up();

    $user->unsetRelation('roles');
    expect($user->hasRole('instrumentist'))->toBeTrue();
});

test('no falla ni asigna nada cuando el rol legacy no tiene Role Spatie correspondiente', function () {
    // Inserta directo por DB, sin pasar por UserFactory::configure() (que crea y asigna
    // el Role automáticamente) -- así se simula un usuario legacy real cuyo `role` nunca
    // tuvo un Role Spatie correspondiente creado.
    $hospital = Hospital::factory()->create();
    $attributes = User::factory()->raw(['hospital_id' => $hospital->id, 'role' => 'un-rol-inexistente']);
    User::insert($attributes);
    $user = User::where('hospital_id', $hospital->id)->firstOrFail();

    expect(fn () => loadMigrateLegacyRolesMigration()->up())->not->toThrow(Throwable::class);

    $user->unsetRelation('roles');
    expect($user->getRoleNames())->toBeEmpty();
});

test('es idempotente: correr up() dos veces no duplica asignaciones', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);

    $migration = loadMigrateLegacyRolesMigration();
    $migration->up();
    $migration->up();

    expect(DB::table('model_has_roles')->where('model_id', $user->id)->count())->toBe(1);
});

test('no reasigna ni falla si el usuario ya tenía el rol Spatie asignado manualmente', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');

    expect(fn () => loadMigrateLegacyRolesMigration()->up())->not->toThrow(Throwable::class);

    expect(DB::table('model_has_roles')->where('model_id', $user->id)->count())->toBe(1);
});

test('usuarios sin role legacy (vacio) se ignoran', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // `role` es NOT NULL en el esquema, así que "sin rol legacy" en datos reales es
    // cadena vacía, no NULL. Inserta directo por DB para evitar el auto-assign de
    // UserFactory::configure().
    $hospital = Hospital::factory()->create();
    $attributes = User::factory()->raw(['hospital_id' => $hospital->id, 'role' => '']);
    User::insert($attributes);
    $withEmptyRole = User::where('hospital_id', $hospital->id)->firstOrFail();

    loadMigrateLegacyRolesMigration()->up();

    $withEmptyRole->unsetRelation('roles');
    expect($withEmptyRole->getRoleNames())->toBeEmpty();
});
