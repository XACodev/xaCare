<?php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\ProcedureType;
use App\Modules\QxLog\Models\RoleRate;
use App\Modules\QxLog\Models\SurgicalRole;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'pricing.manage', 'guard_name' => 'web']);
});

test('agrega una tarifa por tipo de cirugia para un rol, creando el tipo si no existe', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole('admin');
    $admin->givePermissionTo('pricing.manage');
    $this->actingAs($admin);

    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Cirujano']);

    Volt::test('qxlog.pricing.procedure-types')
        ->set('selected_role_id', $role->id)
        ->set('new_rate_type_query', 'Cesárea')
        ->set('new_rate_amount', 1800)
        ->call('addRate')
        ->assertHasNoErrors();

    $type = ProcedureType::where('hospital_id', $hospital->id)->where('name', 'Cesárea')->first();
    expect($type)->not->toBeNull();
    expect(RoleRate::where('surgical_role_id', $role->id)->where('procedure_type_id', $type->id)->where('base_rate', 1800)->exists())
        ->toBeTrue();
});

test('reutiliza un tipo de cirugia existente en vez de duplicarlo', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole('admin');
    $admin->givePermissionTo('pricing.manage');
    $this->actingAs($admin);

    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create();
    $existing = ProcedureType::factory()->for($hospital, 'hospital')->create(['name' => 'Apendicectomía']);

    Volt::test('qxlog.pricing.procedure-types')
        ->set('selected_role_id', $role->id)
        ->set('new_rate_type_id', $existing->id)
        ->set('new_rate_type_query', 'Apendicectomía')
        ->set('new_rate_amount', 900)
        ->call('addRate');

    expect(ProcedureType::where('hospital_id', $hospital->id)->where('name', 'Apendicectomía')->count())->toBe(1);
});

test('la lista de tarifas por tipo ignora tarifas personales de un usuario (Finding 5)', function () {
    // $rates filtraba por surgical_role_id + whereNotNull('procedure_type_id') pero NO
    // por whereNull('user_id'), a diferencia de addRate/removeRate que sí lo hacen. Si
    // existe una tarifa PERSONAL (user_id != null) con procedure_type_id, esta página
    // la mostraba como si fuera la tarifa default del rol, y removeRate podía terminar
    // operando sobre la fila equivocada.
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole('admin');
    $admin->givePermissionTo('pricing.manage');
    $this->actingAs($admin);

    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Cirujano']);
    $type = ProcedureType::factory()->for($hospital, 'hospital')->create(['name' => 'Apendicectomia']);
    $surgeon = User::factory()->create(['hospital_id' => $hospital->id]);

    // Tarifa PERSONAL (user_id != null) para ese rol + tipo -- NO debe aparecer aquí.
    $personalRate = RoleRate::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_role_id' => $role->id,
        'user_id' => $surgeon->id,
        'procedure_type_id' => $type->id,
        'base_rate' => 999,
    ]);

    $component = Volt::test('qxlog.pricing.procedure-types')->set('selected_role_id', $role->id);

    expect($component->instance()->rates)->toHaveCount(0);

    // La tarifa personal sigue intacta: la página no debe permitir tocarla desde aquí.
    expect(RoleRate::withoutGlobalScopes()->where('id', $personalRate->id)->exists())->toBeTrue();
});

test('un admin de otro hospital no puede seleccionar un rol ajeno ni borrar una tarifa ajena', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospitalA->id]);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole('admin');
    $admin->givePermissionTo('pricing.manage');

    SurgicalRole::factory()->for($hospitalA, 'hospital')->create(['name' => 'Circulante', 'sort_order' => 1]);
    $foreignRole = SurgicalRole::factory()->for($hospitalB, 'hospital')->create(['name' => 'Cirujano', 'sort_order' => 1]);
    $foreignType = ProcedureType::factory()->for($hospitalB, 'hospital')->create(['name' => 'Colecistectomía']);
    $foreignRate = RoleRate::factory()->create([
        'hospital_id' => $hospitalB->id,
        'surgical_role_id' => $foreignRole->id,
        'user_id' => null,
        'procedure_type_id' => $foreignType->id,
        'base_rate' => 500,
    ]);

    $this->actingAs($admin);

    Volt::test('qxlog.pricing.procedure-types')
        ->set('selected_role_id', $foreignRole->id)
        ->set('new_rate_type_query', 'Colecistectomía')
        ->set('new_rate_amount', 999)
        ->call('addRate')
        ->assertHasErrors(['selected_role_id']);

    expect(RoleRate::where('surgical_role_id', $foreignRole->id)->where('base_rate', 999)->exists())->toBeFalse();

    Volt::test('qxlog.pricing.procedure-types')
        ->call('removeRate', $foreignRate->id)
        ->assertStatus(403);

    expect(RoleRate::withoutGlobalScopes()->where('id', $foreignRate->id)->exists())->toBeTrue();
});
