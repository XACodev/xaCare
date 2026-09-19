<?php

use App\Models\AdmissionType;
use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'settings.manage', 'guard_name' => 'web']);
});

function businessHourSettingsAdmin(Hospital $hospital): User
{
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);
    $admin->givePermissionTo('settings.manage');

    return $admin;
}

it('shows business hour mapping fields when addon is active', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_business_hours']]);
    $admin = businessHourSettingsAdmin($hospital);
    $tipo = AdmissionType::factory()->for($hospital)->create();

    Volt::actingAs($admin)->test('settings.admission-types-edit', ['admissionType' => $tipo])
        ->assertSeeText(__('Resolución de horario hábil/inhábil'));
});

it('hides business hour mapping fields when addon is not active', function () {
    $hospital = Hospital::factory()->create();
    $admin = businessHourSettingsAdmin($hospital);
    $tipo = AdmissionType::factory()->for($hospital)->create();

    Volt::actingAs($admin)->test('settings.admission-types-edit', ['admissionType' => $tipo])
        ->assertDontSeeText(__('Resolución de horario hábil/inhábil'));
});

it('saves business hour and after hours slugs', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_business_hours']]);
    $admin = businessHourSettingsAdmin($hospital);

    $tipo = AdmissionType::factory()->for($hospital)->create();
    AdmissionType::factory()->for($hospital)->create(['slug' => 'emergencia-habil']);
    AdmissionType::factory()->for($hospital)->create(['slug' => 'emergencia-inhabil']);

    Volt::actingAs($admin)->test('settings.admission-types-edit', ['admissionType' => $tipo])
        ->set('businessHourTypeSlug', 'emergencia-habil')
        ->set('afterHoursTypeSlug', 'emergencia-inhabil')
        ->call('saveBusinessHourSlugs')
        ->assertHasNoErrors();

    $tipo->refresh();
    expect($tipo->business_hour_type_slug)->toBe('emergencia-habil');
    expect($tipo->after_hours_type_slug)->toBe('emergencia-inhabil');
    expect($tipo->isBusinessHourBase())->toBeTrue();
});

it('validates that mapped slugs exist in the hospital', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_business_hours']]);
    $admin = businessHourSettingsAdmin($hospital);
    $tipo = AdmissionType::factory()->for($hospital)->create();

    Volt::actingAs($admin)->test('settings.admission-types-edit', ['admissionType' => $tipo])
        ->set('businessHourTypeSlug', 'no-existe')
        ->call('saveBusinessHourSlugs')
        ->assertHasErrors(['businessHourTypeSlug']);
});

it('clears mapping when empty values are saved', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_business_hours']]);
    $admin = businessHourSettingsAdmin($hospital);
    $tipo = AdmissionType::factory()->for($hospital)->create([
        'business_hour_type_slug' => 'emergencia-habil',
        'after_hours_type_slug' => 'emergencia-inhabil',
    ]);

    Volt::actingAs($admin)->test('settings.admission-types-edit', ['admissionType' => $tipo])
        ->set('businessHourTypeSlug', '')
        ->set('afterHoursTypeSlug', '')
        ->call('saveBusinessHourSlugs')
        ->assertHasNoErrors();

    $tipo->refresh();
    expect($tipo->business_hour_type_slug)->toBeNull();
    expect($tipo->after_hours_type_slug)->toBeNull();
    expect($tipo->isBusinessHourBase())->toBeFalse();
});
