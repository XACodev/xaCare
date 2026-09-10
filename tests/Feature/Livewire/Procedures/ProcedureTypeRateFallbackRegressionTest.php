<?php

// tests/Feature/Livewire/Procedures/ProcedureTypeRateFallbackRegressionTest.php
//
// Comportamiento FINAL, ya no transicional: Task 3 del plan de catálogos qxlog
// reconectó create.blade.php y edit.blade.php con un ProcedureType real
// (procedure_type_query/procedure_type_id resueltos vía $resolveProcedureType,
// con alta automática por firstOrCreate si no existe todavía). Ahora que
// RateResolutionService::resolve() recibe ese ProcedureType real en vez de
// null, un RoleRate configurado para ese procedure_type_id específico SÍ debe
// tener prioridad sobre la tarifa default del rol (procedure_type_id null).
//
// Este test reemplaza la regresión intencional anterior (que verificaba lo
// contrario: que la tarifa por tipo se ignoraba por completo mientras el
// campo era texto libre no enlazado). No se borra el archivo para conservar
// la trazabilidad del caso de prueba a través del plan.
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

test('create.blade.php: usa el RoleRate configurado para el tipo de procedimiento cuando existe', function () {
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

    // Tarifa por tipo de procedimiento -- ahora debe tener prioridad sobre la default.
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
        ->set('procedure_type_query', $procedureType->name)
        ->set('assignments.0.role_id', $role->id)
        ->call('save')
        ->assertHasNoErrors();

    $assignment = SurgicalAssignment::where('surgical_role_id', $role->id)->first();

    expect($assignment)->not->toBeNull();
    expect((float) $assignment->calculated_amount)->toBe(500.0);

    $case = SurgicalCase::find($assignment->surgical_case_id);
    expect($case->procedure_type_id)->toBe($procedureType->id);
});

test('edit.blade.php: usa el RoleRate configurado para el tipo de procedimiento cuando existe', function () {
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

    // Tarifa por tipo de procedimiento -- ahora debe tener prioridad sobre la default.
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
        'procedure_type_id' => $procedureType->id,
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

    expect((float) $assignment->fresh()->calculated_amount)->toBe(500.0);
});
