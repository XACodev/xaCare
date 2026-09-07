<?php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\PayoutBatch;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\SurgicalRole;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

function makeAdmin(Hospital $hospital): User
{
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false]);
    $admin->assignRole('admin');

    return $admin;
}

test('hospital admin only sees procedures from their own hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $adminA = makeAdmin($hospitalA);
    $this->actingAs($adminA);
    SurgicalCase::factory()->create(['patient_name' => 'Paciente A']);

    $adminB = makeAdmin($hospitalB);
    $this->actingAs($adminB);
    SurgicalCase::factory()->create(['patient_name' => 'Paciente B']);

    $this->actingAs($adminA);

    $procedures = Volt::test('reports.procedures')->get('procedures');

    expect($procedures)->toHaveCount(1)
        ->and($procedures->first()->patient_name)->toBe('Paciente A');
});

test('hospital admin only sees payouts from their own hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $adminA = makeAdmin($hospitalA);
    $this->actingAs($adminA);
    PayoutBatch::factory()->create(['payee_id' => User::factory()->create(['hospital_id' => $hospitalA->id])->id]);

    $adminB = makeAdmin($hospitalB);
    $this->actingAs($adminB);
    PayoutBatch::factory()->create(['payee_id' => User::factory()->create(['hospital_id' => $hospitalB->id])->id]);

    $this->actingAs($adminA);

    $payouts = Volt::test('reports.payouts')->get('payouts');

    expect($payouts)->toHaveCount(1);
});

test('hospital admin only sees distribution rows from their own hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $adminA = makeAdmin($hospitalA);
    $this->actingAs($adminA);
    $roleA = SurgicalRole::factory()->create(['name' => 'Cirujano']);
    $caseA = SurgicalCase::factory()->create();
    SurgicalAssignment::factory()->create([
        'surgical_case_id' => $caseA->id,
        'surgical_role_id' => $roleA->id,
        'user_id' => $adminA->id,
    ]);

    $adminB = makeAdmin($hospitalB);
    $this->actingAs($adminB);
    $roleB = SurgicalRole::factory()->create(['name' => 'Cirujano']);
    $caseB = SurgicalCase::factory()->create();
    SurgicalAssignment::factory()->create([
        'surgical_case_id' => $caseB->id,
        'surgical_role_id' => $roleB->id,
        'user_id' => $adminB->id,
    ]);

    $this->actingAs($adminA);

    $distribution = Volt::test('reports.distribution')->get('distribution');

    expect($distribution)->toHaveCount(1)
        ->and($distribution->first()['user'])->toBe($adminA->name);
});

test('non admin cannot view hospital reports', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false]);
    $this->actingAs($user);

    Volt::test('reports.procedures')->assertStatus(403);
});

test('exporting the procedures report streams a csv file', function () {
    $hospital = Hospital::factory()->create();
    $admin = makeAdmin($hospital);
    $this->actingAs($admin);

    SurgicalCase::factory()->create();

    Volt::test('reports.procedures')->call('exportCsv')->assertFileDownloaded();
});
