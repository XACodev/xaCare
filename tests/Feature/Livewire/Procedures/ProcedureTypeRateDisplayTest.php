<?php

// tests/Feature/Livewire/Procedures/ProcedureTypeRateDisplayTest.php
//
// Regresion de la revision final del plan de catalogos qxlog (Finding 6): las dos
// mitades del camino ya estaban cubiertas por separado -- ProcedureTypeRatesTest.php
// (la UI de precios) y ProcedureTypeRateFallbackRegressionTest.php (que el dinero
// correcto se usa en create/edit) -- pero nada probaba el recorrido END-TO-END
// completo, incluyendo la pata de DISPLAY (tablero + reporte). Ese hueco es
// exactamente por donde se coló sin detectar el Finding 1 (el 500 de nombre
// duplicado) durante la revision.
//
// El registro del procedimiento escribe el nombre EXACTO del tipo en
// procedure_type_query sin llamar a selectProcedureType(), para ejercitar también el
// fix del Finding 3 (preview y guardado deben resolver el mismo ProcedureType por
// nombre cuando procedure_type_id sigue null).
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\ProcedureType;
use App\Modules\QxLog\Models\RoleRate;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\SurgicalRole;
use App\Modules\Reports\Services\ReportService;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('registrar un procedimiento escribiendo el nombre del tipo usa la tarifa especifica del tipo y el nombre se ve en el tablero y en el reporte', function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['surgeries.view'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $hospital = Hospital::factory()->create();
    $instrumentist = User::factory()->create(['role' => 'instrumentist', 'hospital_id' => $hospital->id]);
    $instrumentist->givePermissionTo('surgeries.view');
    // El cirujano es OTRA persona: si se asignara al propio instrumentista logueado en
    // esta fila, save() fuerza el rol de vuelta a "Instrumentista" (regla de negocio no
    // relacionada con este test -- ver comentario de $save en create.blade.php), lo que
    // enmascararía la tarifa que este test quiere probar.
    $surgeon = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);

    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create([
        'name' => 'Cirujano Principal', 'is_payable' => true,
    ]);

    $procedureType = ProcedureType::factory()->for($hospital, 'hospital')->create(['name' => 'Apendicectomia']);

    // Tarifa default del rol (procedure_type_id null) -- NO debe ser la que se use.
    RoleRate::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_role_id' => $role->id,
        'user_id' => null,
        'procedure_type_id' => null,
        'base_rate' => 100,
    ]);

    // Tarifa especifica del tipo -- esta SI debe usarse.
    RoleRate::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_role_id' => $role->id,
        'user_id' => null,
        'procedure_type_id' => $procedureType->id,
        'base_rate' => 750,
    ]);

    $patient = Patient::factory()->create(['hospital_id' => $hospital->id]);

    $this->actingAs($instrumentist);

    // Se escribe el nombre exacto del tipo (mismo case) pero NUNCA se llama a
    // selectProcedureType(): procedure_type_id se queda null, tanto el preview como el
    // guardado deben resolverlo por nombre y encontrar el mismo ProcedureType.
    $component = Volt::test('qxlog.procedures.create')
        ->call('selectPatient', $patient->id)
        ->set('procedure_type_query', 'Apendicectomia')
        ->set('assignments.0.role_id', $role->id)
        ->set('assignments.0.user_id', $surgeon->id)
        ->set('assignments.0.user_query', $surgeon->name);

    // El preview (antes de guardar) ya debe reflejar la tarifa especifica del tipo,
    // no la default del rol -- el fix del Finding 3.
    expect($component->instance()->previewAmount(0))->toBe(750.0);

    $component->call('save')->assertHasNoErrors();

    $assignment = SurgicalAssignment::where('surgical_role_id', $role->id)->first();
    expect($assignment)->not->toBeNull();
    expect((float) $assignment->calculated_amount)->toBe(750.0);

    $case = SurgicalCase::find($assignment->surgical_case_id);
    expect($case->procedure_type_id)->toBe($procedureType->id);

    // No se creo un ProcedureType duplicado: sigue habiendo un solo "Apendicectomia".
    expect(ProcedureType::where('hospital_id', $hospital->id)->where('name', 'Apendicectomia')->count())->toBe(1);

    // Pata de DISPLAY 1: el tablero de cirugias muestra el nombre del tipo.
    Volt::test('qxlog.surgeries.board')
        ->set('view', 'list')
        ->assertSee('Apendicectomia');

    // Pata de DISPLAY 2: el reporte de procedimientos incluye el nombre del tipo.
    $reportRows = app(ReportService::class)->proceduresByDateRange(null, null);
    $matching = $reportRows->firstWhere('id', $case->id);
    expect($matching)->not->toBeNull();
    expect($matching->procedureType?->name)->toBe('Apendicectomia');
});
