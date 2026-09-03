<?php

use App\Models\Activity;
use App\Models\Admission;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\RoleRate;
use App\Models\SurgicalAssignment;
use App\Models\SurgicalCase;
use App\Models\SurgicalRole;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    foreach (['procedures.edit', 'pricing.manage'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $adminRole->givePermissionTo(['procedures.edit', 'pricing.manage']);
});

function makeFase1PlatformAdmin(): User
{
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true, 'role' => 'admin']);
    $superAdmin->assignRole('admin');

    return $superAdmin;
}

function makeFase1HospitalAdmin(?Hospital $hospital = null): User
{
    $hospital ??= Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false, 'role' => 'admin']);
    $admin->assignRole('admin');

    return $admin;
}

test('super admin cannot edit (save) a SurgicalCase / SurgicalAssignment of another hospital', function () {
    $hospital = Hospital::factory()->create();
    $role = SurgicalRole::factory()->create(['hospital_id' => $hospital->id]);

    $case = SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'procedure_type' => 'Original',
    ]);
    $assignment = SurgicalAssignment::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_case_id' => $case->id,
        'surgical_role_id' => $role->id,
    ]);

    $superAdmin = makeFase1PlatformAdmin();
    $this->actingAs($superAdmin);

    expect(fn () => $case->update(['procedure_type' => 'Tampered']))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    expect(fn () => $assignment->update(['note' => 'tampered']))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('super admin cannot delete a SurgicalCase', function () {
    $hospital = Hospital::factory()->create();
    $case = SurgicalCase::factory()->create(['hospital_id' => $hospital->id]);

    $superAdmin = makeFase1PlatformAdmin();
    $this->actingAs($superAdmin);

    expect(fn () => $case->delete())
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('super admin cannot save/create a RoleRate', function () {
    $hospital = Hospital::factory()->create();
    $role = SurgicalRole::factory()->create(['hospital_id' => $hospital->id]);

    $superAdmin = makeFase1PlatformAdmin();
    $this->actingAs($superAdmin);

    expect(fn () => RoleRate::create([
        'hospital_id' => $hospital->id,
        'surgical_role_id' => $role->id,
        'base_rate' => 999,
        'active' => true,
    ]))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('super admin cannot modify use_pay_scheme of a User via pricing.instrumentist toggle', function () {
    $hospital = Hospital::factory()->create();
    $target = User::factory()->create(['hospital_id' => $hospital->id, 'use_pay_scheme' => false]);

    $superAdmin = makeFase1PlatformAdmin();
    $this->actingAs($superAdmin);

    Volt::test('pricing.instrumentist')
        ->call('toggle', $target->id)
        ->assertForbidden();

    expect($target->fresh()->use_pay_scheme)->toBeFalse();
});

test('super admin cannot save a surgical case via procedures.edit', function () {
    $hospital = Hospital::factory()->create();
    $case = SurgicalCase::factory()->create([
        'hospital_id' => $hospital->id,
        'status' => 'pending',
    ]);

    $superAdmin = makeFase1PlatformAdmin();
    $superAdmin->givePermissionTo('procedures.edit');
    $this->actingAs($superAdmin);

    Volt::test('procedures.edit', ['procedure' => $case])
        ->set('patient_name', 'Tampered')
        ->call('save')
        ->assertForbidden();

    expect($case->fresh()->patient_name)->not->toBe('Tampered');
});

test('super admin cannot delete a surgical case via procedures.index', function () {
    $hospital = Hospital::factory()->create();
    $case = SurgicalCase::factory()->create(['hospital_id' => $hospital->id]);

    $superAdmin = makeFase1PlatformAdmin();
    $this->actingAs($superAdmin);

    Volt::test('procedures.index')
        ->set('procedure_to_delete', $case->id)
        ->call('delete')
        ->assertForbidden();

    expect(SurgicalCase::withoutGlobalScopes()->find($case->id))->not->toBeNull();
});

test('super admin cannot saveBaseRate on pricing.settings for another hospital', function () {
    $hospital = Hospital::factory()->create();
    $role = SurgicalRole::factory()->create(['hospital_id' => $hospital->id, 'active' => true]);

    $superAdmin = makeFase1PlatformAdmin();
    $superAdmin->givePermissionTo('pricing.manage');
    $this->actingAs($superAdmin);

    Volt::test('pricing.settings')
        ->set('selected_role_id', $role->id)
        ->set('base_rate', 999)
        ->call('saveBaseRate')
        ->assertForbidden();

    expect(RoleRate::withoutGlobalScopes()->where('surgical_role_id', $role->id)->exists())->toBeFalse();
});

test('hospital admin cannot create an admission pointing at a patient from another hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    $adminA = makeFase1HospitalAdmin($hospitalA);
    $patientB = Patient::factory()->create(['hospital_id' => $hospitalB->id]);

    $this->actingAs($adminA);

    Volt::test('admissions.create')
        ->set('patient_id', $patientB->id)
        ->set('fecha_ingreso', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['patient_id']);

    expect(Admission::withoutGlobalScopes()->where('patient_id', $patientB->id)->exists())->toBeFalse();
});

test('activity logged for a surgical assignment carries hospital_id and is scoped between hospitals', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    $adminA = makeFase1HospitalAdmin($hospitalA);
    $adminB = makeFase1HospitalAdmin($hospitalB);

    $role = SurgicalRole::factory()->create(['hospital_id' => $hospitalA->id]);
    $case = SurgicalCase::factory()->create(['hospital_id' => $hospitalA->id]);
    $assignment = SurgicalAssignment::factory()->create([
        'hospital_id' => $hospitalA->id,
        'surgical_case_id' => $case->id,
        'surgical_role_id' => $role->id,
        'note' => 'original',
    ]);

    $this->actingAs($adminA);
    $assignment->update(['note' => 'honorario ajustado']);

    $activity = Activity::withoutGlobalScopes()
        ->where('subject_type', SurgicalAssignment::class)
        ->where('subject_id', $assignment->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->hospital_id)->toBe($hospitalA->id);

    $this->actingAs($adminB);

    expect(Activity::pluck('id'))->not->toContain($activity->id);
});
