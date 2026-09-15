<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'settings.manage', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'surgeries.view', 'guard_name' => 'web']);
});

function mobileBackAdmin(): User
{
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->assignRole('admin');
    $admin->givePermissionTo(['settings.manage', 'surgeries.view']);

    return $admin;
}

test('patient show has a named-route mobile back to patients', function () {
    $admin = mobileBackAdmin();
    $patient = Patient::factory()->create(['hospital_id' => $admin->hospital_id]);
    $this->actingAs($admin);

    Volt::test('patients.show', ['patient' => $patient->slug])
        ->assertSee(__('Pacientes'))
        ->assertSee(route('patients.index'), false);
});

test('admission create step 0 has a named-route mobile back to the ingresos list', function () {
    $admin = mobileBackAdmin();
    $this->actingAs($admin);

    Volt::test('admissions.create')
        ->assertSet('currentStep', 0)
        ->assertSee(__('Ingresos'))
        ->assertSee(route('admissions.index'), false);
});

test('admission create mid-flow back goes to the previous wizard step', function () {
    $admin = mobileBackAdmin();
    \App\Support\AdmissionTypeSeeder::seedDefaultsFor($admin->hospital);
    $tipo = \App\Models\AdmissionType::query()
        ->where('hospital_id', $admin->hospital_id)
        ->where('slug', 'coex')
        ->firstOrFail();

    $this->actingAs($admin);

    Volt::test('admissions.create')
        ->set('admissionTypeId', $tipo->id)
        ->call('nextStep')
        ->assertSet('currentStep', 1)
        ->assertSee(__('Atrás'))
        ->call('previousStep')
        ->assertSet('currentStep', 0);
});

test('settings organization has a named-route mobile back to the configurations hub', function () {
    $admin = mobileBackAdmin();
    $this->actingAs($admin);

    Volt::test('settings.organization')
        ->assertSee(__('Configurations'))
        ->assertSee(route('settings.index'), false);
});

test('surgery calendar has a named-route mobile back to the board', function () {
    $admin = mobileBackAdmin();
    $this->actingAs($admin);

    Volt::test('qxlog.surgeries.calendar')
        ->assertSee(__('Surgeries'))
        ->assertSee(route('surgeries.board'), false);
});
