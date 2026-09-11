<?php
// tests/Feature/Tenancy/AppearAsSuggestionFilterTest.php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\SurgicalCase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'search.appear_as_suggestion', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'surgeries.schedule', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'procedures.create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'procedures.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'procedures.edit', 'guard_name' => 'web']);
});

test('procedures.create solo sugiere usuarios con el permiso de aparecer en busqueda', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin', 'name' => 'Ana Visible']);
    $admin->givePermissionTo(['procedures.create', 'procedures.view']);

    $visible = User::factory()->create(['hospital_id' => $hospital->id, 'name' => 'Ana Visible Dos']);
    $visible->givePermissionTo('search.appear_as_suggestion');

    // role 'admin': es un rol global que CoreRoleProvisioner no toca, así que no
    // hereda 'search.appear_as_suggestion' por defecto como sí lo hace 'doctor'
    // (rol por defecto de la factory) desde la Task 1.
    $hidden = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin', 'name' => 'Ana Oculta']);

    $this->actingAs($admin);

    // No existe una propiedad publica top-level `user_query` en este componente
    // (el query de cada fila vive en `assignments.{index}.user_query`); la
    // sugerencia se ejercita llamando directamente al computed `userSuggestions`.
    $component = Volt::test('qxlog.procedures.create');

    $names = collect(($component->instance()->userSuggestions)('Ana'))->pluck('name')->all();

    expect($names)->toContain('Ana Visible Dos');
    expect($names)->not->toContain('Ana Oculta');
});

test('surgeries.schedule (crear) solo sugiere usuarios con el permiso', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin', 'name' => 'Bea Visible']);
    $admin->givePermissionTo('surgeries.schedule');

    $visible = User::factory()->create(['hospital_id' => $hospital->id, 'name' => 'Bea Visible Dos']);
    $visible->givePermissionTo('search.appear_as_suggestion');

    // role 'admin' por la misma razón que en el test anterior: evita el permiso
    // heredado por defecto del rol 'doctor' de la factory.
    $hidden = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin', 'name' => 'Bea Oculta']);

    $this->actingAs($admin);

    $component = Volt::test('qxlog.surgeries.schedule');

    $names = collect(($component->instance()->userSuggestions)('Bea'))->pluck('name')->all();

    expect($names)->toContain('Bea Visible Dos');
    expect($names)->not->toContain('Bea Oculta');
});

test('procedures.edit (selector de asignacion) solo lista usuarios con el permiso', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->givePermissionTo(['procedures.create', 'procedures.view', 'procedures.edit']);

    $visible = User::factory()->create(['hospital_id' => $hospital->id, 'name' => 'Cata Visible']);
    $visible->givePermissionTo('search.appear_as_suggestion');

    // role 'admin' por la misma razón que en los tests anteriores.
    $hidden = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin', 'name' => 'Cata Oculta']);

    $patient = Patient::factory()->for($hospital, 'hospital')->create();
    $case = SurgicalCase::factory()->for($hospital, 'hospital')->create(['patient_id' => $patient->id]);
    \App\Modules\QxLog\Models\SurgicalAssignment::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_case_id' => $case->id,
        'surgical_role_id' => \App\Modules\QxLog\Models\SurgicalRole::factory()->create(['hospital_id' => $hospital->id]),
        'user_id' => $visible->id,
    ]);

    $this->actingAs($admin);

    Volt::test('qxlog.procedures.edit', ['procedure' => $case])
        ->assertSee('Cata Visible')
        ->assertDontSee('Cata Oculta');
});
