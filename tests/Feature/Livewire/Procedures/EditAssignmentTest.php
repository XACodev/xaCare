<?php
// tests/Feature/Livewire/Procedures/EditAssignmentTest.php
use App\Models\Hospital;
use App\Modules\QxLog\Models\RateModifier;
use App\Modules\QxLog\Models\RoleRate;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\SurgicalRole;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::create(['name' => 'procedures.edit', 'guard_name' => 'web']);
});

test('rejects editing a case whose status changed to non-pending after load', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin->givePermissionTo('procedures.edit');

    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Cirujano']);
    $roleRate = RoleRate::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_role_id' => $role->id,
        'user_id' => null,
        'procedure_type' => null,
        'base_rate' => 500,
    ]);

    $case = SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'procedure_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'duration_minutes' => 120,
        'procedure_type' => 'Apendicectomia',
        'status' => 'pending',
        'calculated_amount' => 500,
    ]);

    $assignment = SurgicalAssignment::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_case_id' => $case->id,
        'surgical_role_id' => $role->id,
        'user_id' => $admin->id,
        'calculated_amount' => 500,
    ]);

    $this->actingAs($admin);

    $component = Volt::test('qxlog.procedures.edit', ['procedure' => $case]);

    // Simulate the case being liquidated by another process before save.
    $case->update(['status' => 'paid']);

    $component
        ->set('patient_name', 'Nombre Corregido')
        ->call('save')
        ->assertStatus(403);

    expect($case->fresh()->patient_name)->not->toBe('Nombre Corregido');
});

test('rejects assignments ids that do not belong to the edited case', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin->givePermissionTo('procedures.edit');

    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Cirujano']);
    RoleRate::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_role_id' => $role->id,
        'user_id' => null,
        'procedure_type' => null,
        'base_rate' => 500,
    ]);

    $case = SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'procedure_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'duration_minutes' => 120,
        'procedure_type' => 'Apendicectomia',
        'status' => 'pending',
    ]);

    $assignment = SurgicalAssignment::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_case_id' => $case->id,
        'surgical_role_id' => $role->id,
        'user_id' => $admin->id,
        'calculated_amount' => 500,
    ]);

    $otherCase = SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'status' => 'pending',
    ]);
    $otherAssignment = SurgicalAssignment::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_case_id' => $otherCase->id,
        'surgical_role_id' => $role->id,
        'calculated_amount' => 999,
    ]);

    $this->actingAs($admin);

    Volt::test('qxlog.procedures.edit', ['procedure' => $case])
        ->set('assignments.0.id', $otherAssignment->id)
        ->call('save')
        ->assertStatus(403);

    expect((float) $otherAssignment->fresh()->calculated_amount)->toBe(999.0);
});

test('rejects an assigned user from another hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospitalA->id]);
    $admin->givePermissionTo('procedures.edit');

    $role = SurgicalRole::factory()->for($hospitalA, 'hospital')->create(['name' => 'Cirujano']);
    RoleRate::factory()->create([
        'hospital_id' => $hospitalA->id,
        'surgical_role_id' => $role->id,
        'user_id' => null,
        'procedure_type' => null,
        'base_rate' => 500,
    ]);

    $case = SurgicalCase::factory()->create([
        'hospital_id' => $hospitalA->id,
        'procedure_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'duration_minutes' => 120,
        'procedure_type' => 'Apendicectomia',
        'status' => 'pending',
    ]);

    $assignment = SurgicalAssignment::factory()->create([
        'hospital_id' => $hospitalA->id,
        'surgical_case_id' => $case->id,
        'surgical_role_id' => $role->id,
        'user_id' => $admin->id,
        'calculated_amount' => 500,
    ]);

    $foreignUser = User::factory()->create(['hospital_id' => $hospitalB->id]);

    $this->actingAs($admin);

    $component = Volt::test('qxlog.procedures.edit', ['procedure' => $case]);
    $component->set('assignments.0.user_id', $foreignUser->id);

    expect($component->get('assignments')[0]['user_id'])->toBe($foreignUser->id);

    $component->call('save')
        ->assertStatus(403);

    expect($assignment->fresh()->user_id)->toBe($admin->id);
});

test('editar un caso sin tocar el toggle manual preserva el monto calculado', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin->givePermissionTo('procedures.edit');

    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Cirujano']);
    $roleRate = RoleRate::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_role_id' => $role->id,
        'user_id' => null,
        'procedure_type' => null,
        'base_rate' => 500,
    ]);
    $modifier = RateModifier::factory()->manualToggle('Video')->create([
        'hospital_id' => $hospital->id,
        'role_rate_id' => $roleRate->id,
        'rate_type' => \App\Modules\QxLog\Models\RateModifier::RATE_FIXED_AMOUNT,
        'amount' => 800,
    ]);

    $assignedUser = User::factory()->create(['hospital_id' => $hospital->id]);

    $case = SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'procedure_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'duration_minutes' => 120,
        'procedure_type' => 'Apendicectomia',
        'status' => 'pending',
        'calculated_amount' => 800,
    ]);

    $assignment = SurgicalAssignment::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_case_id' => $case->id,
        'surgical_role_id' => $role->id,
        'user_id' => $assignedUser->id,
        'calculated_amount' => 800,
        'is_courtesy' => false,
        'pricing_snapshot' => [
            'version' => 1,
            'rule' => 'Video',
            'amount' => 800,
            'role_rate_id' => $roleRate->id,
            'base_rate' => 500,
            'candidates' => ['base_rate' => 500, 'Video' => 800],
            'modifiers_evaluated' => [
                [
                    'id' => $modifier->id,
                    'name' => 'Video',
                    'trigger_type' => RateModifier::TRIGGER_MANUAL_TOGGLE,
                    'applies' => true,
                    'amount' => 800.0,
                ],
            ],
            'is_courtesy' => false,
            'duration_minutes' => 120,
            'start_time' => '08:00',
        ],
    ]);

    $this->actingAs($admin);

    $component = Volt::test('qxlog.procedures.edit', ['procedure' => $case]);

    // La fila hidratada debe traer el id del modificador manual como aplicado.
    expect($component->get('assignments')[0]['manual_toggles'])->toBe([$modifier->id]);

    $component
        ->set('patient_name', 'Nombre Corregido')
        ->call('save')
        ->assertHasNoErrors();

    $assignment->refresh();

    expect((float) $assignment->calculated_amount)->toBe(800.0);
    expect($case->fresh()->patient_name)->toBe('Nombre Corregido');
});
