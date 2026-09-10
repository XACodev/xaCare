<?php

// tests/Feature/Livewire/Procedures/ProcedureTypeRateFallbackRegressionTest.php
//
// Regresión intencional: create.blade.php y edit.blade.php todavía llaman a
// RateResolutionService::resolve() con procedureType: null (ver comentarios en
// previewAmount()/save() y recalculate()/save()), porque el campo de tipo de
// procedimiento del formulario sigue siendo texto libre, no un ProcedureType
// enlazado. Mientras eso sea cierto, un RoleRate con procedure_type_id no nulo
// debe ser ignorado por completo y el monto debe caer siempre a la tarifa
// default del rol (procedure_type_id null).
//
// El día que una tarea posterior (Task 3/4 del plan de catálogos qxlog)
// reconecte resolve() con un ProcedureType real, este test debe EMPEZAR A
// FALLAR (porque el monto calculado pasará a ser el de la tarifa por tipo).
// Eso es la señal esperada: cuando falle, actualízalo para reflejar el nuevo
// comportamiento en vez de "arreglarlo" para que vuelva a pasar tal cual.
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\ProcedureType;
use App\Modules\QxLog\Models\RoleRate;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\SurgicalRole;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

test('create.blade.php: ignora el RoleRate por tipo de procedimiento y usa la tarifa default del rol', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);

    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create([
        'name' => 'Cirujano', 'is_payable' => true,
    ]);
    $procedureType = ProcedureType::factory()->for($hospital, 'hospital')->create(['name' => 'Apendicectomia']);

    // Tarifa default del rol (procedure_type_id null).
    RoleRate::factory()->for($role, 'surgicalRole')->create([
        'hospital_id' => $hospital->id,
        'user_id' => null,
        'procedure_type_id' => null,
        'base_rate' => 100,
    ]);

    // Tarifa por tipo de procedimiento -- hoy debe ser ignorada por completo.
    RoleRate::factory()->for($role, 'surgicalRole')->create([
        'hospital_id' => $hospital->id,
        'user_id' => null,
        'procedure_type_id' => $procedureType->id,
        'base_rate' => 500,
    ]);

    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);

    $this->actingAs($admin);

    Volt::test('qxlog.procedures.create')
        ->call('selectPatient', $patient->id)
        ->set('procedure_type', $procedureType->name)
        ->set('assignments.0.role_id', $role->id)
        ->call('save')
        ->assertHasNoErrors();

    $assignment = SurgicalAssignment::where('surgical_role_id', $role->id)->first();

    expect($assignment)->not->toBeNull();
    expect((float) $assignment->calculated_amount)->toBe(100.0);
});

test('edit.blade.php: ignora el RoleRate por tipo de procedimiento y usa la tarifa default del rol', function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::create(['name' => 'procedures.edit', 'guard_name' => 'web']);

    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin->givePermissionTo('procedures.edit');

    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Cirujano']);
    $procedureType = ProcedureType::factory()->for($hospital, 'hospital')->create(['name' => 'Apendicectomia']);

    // Tarifa default del rol (procedure_type_id null).
    RoleRate::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_role_id' => $role->id,
        'user_id' => null,
        'procedure_type_id' => null,
        'base_rate' => 100,
    ]);

    // Tarifa por tipo de procedimiento -- hoy debe ser ignorada por completo.
    RoleRate::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_role_id' => $role->id,
        'user_id' => null,
        'procedure_type_id' => $procedureType->id,
        'base_rate' => 500,
    ]);

    $assignedUser = User::factory()->create(['hospital_id' => $hospital->id]);

    $case = SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'procedure_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'duration_minutes' => 120,
        'procedure_type' => $procedureType->name,
        'status' => 'pending',
        'calculated_amount' => 100,
    ]);

    $assignment = SurgicalAssignment::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_case_id' => $case->id,
        'surgical_role_id' => $role->id,
        'user_id' => $assignedUser->id,
        'calculated_amount' => 100,
    ]);

    $this->actingAs($admin);

    Volt::test('qxlog.procedures.edit', ['procedure' => $case])
        ->set('patient_name', 'Nombre Corregido')
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $assignment->fresh()->calculated_amount)->toBe(100.0);
});
