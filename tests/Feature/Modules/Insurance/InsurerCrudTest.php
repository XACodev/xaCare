<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Modules\Insurance\Models\Insurer;
use App\Services\HospitalPlanService;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->hospital = Hospital::factory()->create();
    app(HospitalPlanService::class)->applyPlan($this->hospital, 'pro');

    $this->admin = User::factory()->create(['hospital_id' => $this->hospital->id]);
    $this->admin->assignRole('admin');
});

test('an admin can create an insurer', function () {
    Volt::actingAs($this->admin)
        ->test('insurance.index')
        ->set('insurer_name', 'Seguros del Caribe')
        ->set('insurer_contact_name', 'Ana Lopez')
        ->set('insurer_contact_email', 'ana@seguroscaribe.com')
        ->call('saveInsurer')
        ->assertHasNoErrors();

    $insurer = Insurer::query()->where('name', 'Seguros del Caribe')->first();

    expect($insurer)->not->toBeNull();
    expect($insurer->hospital_id)->toBe($this->hospital->id);
    expect($insurer->contact_name)->toBe('Ana Lopez');
});

test('an admin can edit an existing insurer', function () {
    $insurer = Insurer::factory()->create([
        'hospital_id' => $this->hospital->id,
        'name' => 'Seguros Antiguos',
    ]);

    Volt::actingAs($this->admin)
        ->test('insurance.index')
        ->call('editInsurer', $insurer->id)
        ->set('insurer_name', 'Seguros Renovados')
        ->call('saveInsurer')
        ->assertHasNoErrors();

    expect($insurer->fresh()->name)->toBe('Seguros Renovados');
});

test('the insurer list shows insurers of the current hospital', function () {
    Insurer::factory()->create(['hospital_id' => $this->hospital->id, 'name' => 'Aseguradora Visible']);

    Volt::actingAs($this->admin)
        ->test('insurance.index')
        ->assertSee('Aseguradora Visible');
});

test('the policies list requires selecting a patient first', function () {
    $patient = Patient::factory()->create(['hospital_id' => $this->hospital->id]);
    $insurer = Insurer::factory()->create(['hospital_id' => $this->hospital->id]);

    Volt::actingAs($this->admin)
        ->test('insurance.index')
        ->set('selected_patient_id', $patient->id)
        ->set('policy_insurer_id', $insurer->id)
        ->set('policy_number', 'POL-0001')
        ->call('savePolicy')
        ->assertHasNoErrors();

    expect(\App\Modules\Insurance\Models\InsurancePolicy::query()
        ->where('patient_id', $patient->id)
        ->where('policy_number', 'POL-0001')
        ->exists())->toBeTrue();
});
