<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * RefreshDatabase ya corre esta migración (sin datos "sucios" todavía) antes de cada test.
 * Para ejercitar su up() contra datos ya guardados con mal formato, se vuelve a invocar
 * explícitamente después de sembrarlos vía query builder (sin pasar por el mutator).
 */
function loadNormalizeUserNamesMigration(): object
{
    return include database_path('migrations/2026_09_08_130000_normalize_user_names.php');
}

test('normalizes previously badly-cased user names', function () {
    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['name' => 'JUan ValdEz']);

    loadNormalizeUserNamesMigration()->up();

    expect($user->fresh()->name)->toBe('Juan Valdez');
});

test('is idempotent when names are already normalized', function () {
    $user = User::factory()->create(['name' => 'Juan Valdez']);

    loadNormalizeUserNamesMigration()->up();

    expect($user->fresh()->name)->toBe('Juan Valdez');
});

test('normalizes names of soft-deleted users too', function () {
    $user = User::factory()->create();
    $user->delete();
    DB::table('users')->where('id', $user->id)->update(['name' => 'ANA lopez']);

    loadNormalizeUserNamesMigration()->up();

    expect(User::withTrashed()->find($user->id)->name)->toBe('Ana Lopez');
});
