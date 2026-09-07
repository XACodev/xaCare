<?php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\PayoutBatch;
use App\Modules\QxLog\Models\SurgicalCase;
use Livewire\Volt\Volt;

test('non platform admins cannot view platform reports', function () {
    $user = User::factory()->create(['is_platform_admin' => false]);
    $this->actingAs($user);

    Volt::test('platform.reports.hospitals')->assertStatus(403);
});

test('platform admin sees hospitals from every tenant', function () {
    Hospital::factory()->create(['name' => 'Hospital Uno']);
    Hospital::factory()->create(['name' => 'Hospital Dos']);

    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $this->actingAs($superAdmin);

    $hospitals = Volt::test('platform.reports.hospitals')->get('hospitals');

    expect($hospitals->count())->toBeGreaterThanOrEqual(2);
});

test('platform admin sees aggregated procedure totals across hospitals', function () {
    // El super admin se crea primero: BelongsToTenant auto-asigna hospital_id
    // desde el usuario autenticado en ese momento aunque se pase null
    // explícitamente, así que crearlo mientras otro admin está logueado le
    // heredaría su hospital_id por accidente.
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $adminA = User::factory()->create(['hospital_id' => $hospitalA->id]);
    $this->actingAs($adminA);
    SurgicalCase::factory()->count(2)->create();

    $adminB = User::factory()->create(['hospital_id' => $hospitalB->id]);
    $this->actingAs($adminB);
    SurgicalCase::factory()->count(3)->create();

    $this->actingAs($superAdmin);

    $byHospital = Volt::test('platform.reports.procedures')->get('byHospital');

    $totals = $byHospital->pluck('total', 'hospital_id');

    expect($totals->get($hospitalA->id))->toBe(2)
        ->and($totals->get($hospitalB->id))->toBe(3);
});

test('platform admin sees estimated revenue by hospital', function () {
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $hospitalA = Hospital::factory()->create();

    $adminA = User::factory()->create(['hospital_id' => $hospitalA->id]);
    $this->actingAs($adminA);
    PayoutBatch::factory()->create(['payee_id' => User::factory()->create(['hospital_id' => $hospitalA->id])->id, 'total_amount' => 500]);
    PayoutBatch::factory()->create(['payee_id' => User::factory()->create(['hospital_id' => $hospitalA->id])->id, 'total_amount' => 250]);

    $this->actingAs($superAdmin);

    $byHospital = Volt::test('platform.reports.revenue')->get('byHospital');

    $total = $byHospital->firstWhere('hospital_id', $hospitalA->id);

    expect((float) $total['total'])->toBe(750.0);
});

test('exporting a platform report streams a csv file', function () {
    Hospital::factory()->create();

    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $this->actingAs($superAdmin);

    Volt::test('platform.reports.hospitals')->call('exportCsv')->assertFileDownloaded();
});
