<?php

use App\Models\BusinessHoliday;
use App\Models\BusinessHour;
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

function businessHoursAdmin(Hospital $hospital): User
{
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);
    $admin->givePermissionTo('settings.manage');

    return $admin;
}

it('blocks access when admissions_business_hours addon is not active', function () {
    $hospital = Hospital::factory()->create();
    $admin = businessHoursAdmin($hospital);

    $this->actingAs($admin)->get(route('settings.business-hours'))->assertForbidden();
});

it('loads default weekly schedule on first visit', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_business_hours']]);
    $admin = businessHoursAdmin($hospital);

    Volt::actingAs($admin)->test('settings.business-hours')
        ->assertSeeText(__('Lunes'))
        ->assertSeeText(__('Domingo'));
});

it('saves weekly business hours', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_business_hours']]);
    $admin = businessHoursAdmin($hospital);

    Volt::actingAs($admin)->test('settings.business-hours')
        ->set('days.0.is_business_day', true)
        ->set('days.0.start_time', '08:00')
        ->set('days.0.end_time', '17:00')
        ->set('days.5.is_business_day', false)
        ->call('saveDays')
        ->assertHasNoErrors();

    $monday = BusinessHour::query()->where('hospital_id', $hospital->id)->where('day_of_week', 0)->first();
    expect($monday)->not->toBeNull();
    expect($monday->is_business_day)->toBeTrue();
    expect($monday->start_time->format('H:i'))->toBe('08:00');
    expect($monday->end_time->format('H:i'))->toBe('17:00');

    $saturday = BusinessHour::query()->where('hospital_id', $hospital->id)->where('day_of_week', 5)->first();
    expect($saturday)->not->toBeNull();
    expect($saturday->is_business_day)->toBeFalse();
});

it('adds and deletes holidays', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_business_hours']]);
    $admin = businessHoursAdmin($hospital);

    $component = Volt::actingAs($admin)->test('settings.business-hours')
        ->set('newHolidayDate', '2025-12-25')
        ->set('newHolidayName', 'Navidad')
        ->call('addHoliday')
        ->assertHasNoErrors();

    expect(BusinessHoliday::query()->where('hospital_id', $hospital->id)->count())->toBe(1);

    $holiday = BusinessHoliday::query()->where('hospital_id', $hospital->id)->first();
    $component->call('deleteHoliday', $holiday->id);

    expect(BusinessHoliday::query()->where('hospital_id', $hospital->id)->count())->toBe(0);
});

it('cannot manage holidays of another hospital', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_business_hours']]);
    $otherHospital = Hospital::factory()->create(['addons' => ['admissions_business_hours']]);
    $admin = businessHoursAdmin($hospital);

    $foreignHoliday = BusinessHoliday::factory()->for($otherHospital)->create();

    Volt::actingAs($admin)->test('settings.business-hours')
        ->call('deleteHoliday', $foreignHoliday->id);

    expect(BusinessHoliday::withoutGlobalScopes()->where('id', $foreignHoliday->id)->exists())->toBeTrue();
});
