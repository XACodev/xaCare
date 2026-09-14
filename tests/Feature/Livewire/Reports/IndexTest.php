<?php

use App\Models\Admission;
use App\Models\AdmissionType;
use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgicalCase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

function reportsDashboardAdmin(Hospital $hospital): User
{
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false]);
    $admin->assignRole('admin');

    return $admin;
}

test('admin can view the reports dashboard', function () {
    $hospital = Hospital::factory()->create();
    $admin = reportsDashboardAdmin($hospital);
    $this->actingAs($admin);

    Volt::test('reports.index')
        ->assertSee(__('Reports'))
        ->assertSee(__('Saved reports'));
});

test('non admin cannot view the reports dashboard', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false]);
    $this->actingAs($user);

    Volt::test('reports.index')->assertStatus(403);
});

test('kpis and room stats only reflect the current hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $adminA = reportsDashboardAdmin($hospitalA);
    $this->actingAs($adminA);
    $roomA = OperatingRoom::factory()->for($hospitalA, 'hospital')->create(['name' => 'Quirófano A']);
    SurgicalCase::factory()->create(['operating_room_id' => $roomA->id, 'calculated_amount' => 500, 'procedure_date' => now()]);

    $adminB = reportsDashboardAdmin($hospitalB);
    $this->actingAs($adminB);
    $roomB = OperatingRoom::factory()->for($hospitalB, 'hospital')->create(['name' => 'Quirófano B']);
    SurgicalCase::factory()->create(['operating_room_id' => $roomB->id, 'calculated_amount' => 900, 'procedure_date' => now()]);

    $this->actingAs($adminA);

    $kpis = Volt::test('reports.index')->get('kpis');
    $roomStats = Volt::test('reports.index')->get('roomStats');

    expect($kpis[0]['value'])->toBe(1)
        ->and($roomStats)->toHaveCount(1)
        ->and($roomStats->first()['name'])->toBe('Quirófano A');
});

test('revenue chart groups the last 14 days by admission type', function () {
    $hospital = Hospital::factory()->create();
    $admin = reportsDashboardAdmin($hospital);
    $this->actingAs($admin);

    $type = AdmissionType::factory()->for($hospital, 'hospital')->create(['name' => 'Hospitalización']);
    $admission = Admission::factory()->for($hospital, 'hospital')->create(['admission_type_id' => $type->id]);
    SurgicalCase::factory()->create([
        'admission_id' => $admission->id,
        'procedure_date' => now(),
        'calculated_amount' => 300,
    ]);

    $chart = Volt::test('reports.index')->get('revenueChart');

    expect($chart)->toHaveCount(14);
    $today = $chart->last();
    expect($today['total'])->toBe(300.0)
        ->and($today['types']->first()['name'])->toBe('Hospitalización');
});
