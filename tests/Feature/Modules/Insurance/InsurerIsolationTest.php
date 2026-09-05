<?php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\Insurance\Models\Insurer;
use App\Services\HospitalPlanService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->hospitalA = Hospital::factory()->create();
    $this->hospitalB = Hospital::factory()->create();
    app(HospitalPlanService::class)->applyPlan($this->hospitalA, 'pro');
    app(HospitalPlanService::class)->applyPlan($this->hospitalB, 'pro');

    $this->adminA = User::factory()->create(['hospital_id' => $this->hospitalA->id]);
    $this->adminA->assignRole('admin');

    $this->adminB = User::factory()->create(['hospital_id' => $this->hospitalB->id]);
    $this->adminB->assignRole('admin');
});

test('an insurer created in hospital A is not visible in hospital B', function () {
    $insurerA = Insurer::factory()->create([
        'hospital_id' => $this->hospitalA->id,
        'name' => 'Aseguradora Solo Hospital A',
    ]);

    $this->actingAs($this->adminB);

    expect(Insurer::query()->find($insurerA->id))->toBeNull();

    $this->actingAs($this->adminA);
    expect(Insurer::query()->find($insurerA->id))->not->toBeNull();

    // La vista de /seguros del hospital B no debe listar la aseguradora del hospital A.
    $this->actingAs($this->adminB)
        ->get(route('modules.insurance'))
        ->assertOk()
        ->assertDontSee('Aseguradora Solo Hospital A');
});

test('deleting an insurer scoped to another hospital fails with a 404', function () {
    $insurerA = Insurer::factory()->create([
        'hospital_id' => $this->hospitalA->id,
    ]);

    Livewire\Volt\Volt::actingAs($this->adminB)
        ->test('insurance.index')
        ->call('deleteInsurer', $insurerA->id)
        ->assertNotFound();

    expect(Insurer::withoutGlobalScopes()->find($insurerA->id))->not->toBeNull();
});
