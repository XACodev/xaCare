<?php

use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * RefreshDatabase ya corre esta migración (sin datos "sucios" todavía) antes de cada test.
 * Para ejercitar su up() contra datos ya guardados con mal formato, se vuelve a invocar
 * explícitamente después de sembrarlos vía query builder (sin pasar por el mutator).
 */
function loadNormalizeNamesMigration(): object
{
    return include database_path('migrations/2026_09_08_130000_normalize_patient_and_user_names.php');
}

test('normalizes previously badly-cased patient and user names', function () {
    $patient = Patient::factory()->create();
    DB::table('patients')->where('id', $patient->id)->update([
        'primer_apellido' => 'DE LA cruz',
        'primer_nombre' => 'JUan',
    ]);

    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['name' => 'JUan ValdEz']);

    loadNormalizeNamesMigration()->up();

    expect($patient->fresh()->primer_apellido)->toBe('De la Cruz');
    expect($patient->fresh()->primer_nombre)->toBe('Juan');
    expect($user->fresh()->name)->toBe('Juan Valdez');
});

test('is idempotent when names are already normalized', function () {
    $patient = Patient::factory()->create(['primer_apellido' => 'Valdez', 'primer_nombre' => 'Juan']);

    loadNormalizeNamesMigration()->up();

    expect($patient->fresh()->primer_apellido)->toBe('Valdez');
    expect($patient->fresh()->primer_nombre)->toBe('Juan');
});

test('normalizes names of soft-deleted patients and users too', function () {
    $patient = Patient::factory()->create();
    $patient->delete();
    DB::table('patients')->where('id', $patient->id)->update(['primer_apellido' => 'GOMEZ']);

    $user = User::factory()->create();
    $user->delete();
    DB::table('users')->where('id', $user->id)->update(['name' => 'ANA lopez']);

    loadNormalizeNamesMigration()->up();

    expect(Patient::withTrashed()->find($patient->id)->primer_apellido)->toBe('Gomez');
    expect(User::withTrashed()->find($user->id)->name)->toBe('Ana Lopez');
});
