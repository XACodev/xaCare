<?php

use App\Models\AdmissionType;
use App\Models\BusinessHoliday;
use App\Models\BusinessHour;
use App\Models\Hospital;
use App\Services\BusinessHoursService;
use Carbon\Carbon;

beforeEach(function () {
    $this->hospital = Hospital::factory()->create();
    $this->user = \App\Models\User::factory()->create([
        'hospital_id' => $this->hospital->id,
        'role' => 'admin',
    ]);
    $this->actingAs($this->user);
});

function setBusinessHours(Hospital $hospital, int $dayOfWeek, string $start, string $end, bool $isBusinessDay = true): void
{
    BusinessHour::updateOrCreate(
        ['hospital_id' => $hospital->id, 'day_of_week' => $dayOfWeek],
        ['start_time' => $start, 'end_time' => $end, 'is_business_day' => $isBusinessDay]
    );
}

it('returns true during configured business hours on a business day', function () {
    // Lunes 15 de septiembre de 2025 a las 10:00
    $datetime = Carbon::parse('2025-09-15 10:00:00');
    expect($datetime->dayOfWeek)->toBe(1); // lunes en Carbon (0=domingo)

    setBusinessHours($this->hospital, 0, '07:00', '18:00'); // 0=lunes en BD

    expect(app(BusinessHoursService::class)->isBusinessHour($this->hospital, $datetime))->toBeTrue();
});

it('returns false before business hours start', function () {
    $datetime = Carbon::parse('2025-09-15 06:00:00');
    setBusinessHours($this->hospital, 0, '07:00', '18:00');

    expect(app(BusinessHoursService::class)->isBusinessHour($this->hospital, $datetime))->toBeFalse();
});

it('returns false after business hours end', function () {
    $datetime = Carbon::parse('2025-09-15 19:00:00');
    setBusinessHours($this->hospital, 0, '07:00', '18:00');

    expect(app(BusinessHoursService::class)->isBusinessHour($this->hospital, $datetime))->toBeFalse();
});

it('returns false on a non business day', function () {
    // Sábado 20 de septiembre de 2025
    $datetime = Carbon::parse('2025-09-20 10:00:00');
    expect($datetime->dayOfWeek)->toBe(6); // sábado en Carbon

    setBusinessHours($this->hospital, 5, '07:00', '18:00', false); // 5=sábado en BD

    expect(app(BusinessHoursService::class)->isBusinessHour($this->hospital, $datetime))->toBeFalse();
});

it('returns false on a holiday regardless of schedule', function () {
    $datetime = Carbon::parse('2025-09-15 10:00:00');
    setBusinessHours($this->hospital, 0, '07:00', '18:00');

    BusinessHoliday::create([
        'hospital_id' => $this->hospital->id,
        'date' => '2025-09-15',
        'name' => 'Feriado de prueba',
    ]);

    expect(app(BusinessHoursService::class)->isBusinessHour($this->hospital, $datetime))->toBeFalse();
});

it('returns false when no schedule exists for the day', function () {
    $datetime = Carbon::parse('2025-09-15 10:00:00');

    expect(app(BusinessHoursService::class)->isBusinessHour($this->hospital, $datetime))->toBeFalse();
});

it('resolves admission type to business hour slug during business hours', function () {
    $datetime = Carbon::parse('2025-09-15 10:00:00');
    setBusinessHours($this->hospital, 0, '07:00', '18:00');

    $baseType = AdmissionType::factory()->for($this->hospital)->create([
        'business_hour_type_slug' => 'emergencia-habil',
        'after_hours_type_slug' => 'emergencia-inhabil',
    ]);

    $businessType = AdmissionType::factory()->for($this->hospital)->create(['slug' => 'emergencia-habil']);
    AdmissionType::factory()->for($this->hospital)->create(['slug' => 'emergencia-inhabil']);

    $resolved = app(BusinessHoursService::class)->resolveAdmissionType($baseType, $datetime);

    expect($resolved)->not->toBeNull();
    expect($resolved->id)->toBe($businessType->id);
});

it('resolves admission type to after hours slug outside business hours', function () {
    $datetime = Carbon::parse('2025-09-15 20:00:00');
    setBusinessHours($this->hospital, 0, '07:00', '18:00');

    $baseType = AdmissionType::factory()->for($this->hospital)->create([
        'business_hour_type_slug' => 'emergencia-habil',
        'after_hours_type_slug' => 'emergencia-inhabil',
    ]);

    AdmissionType::factory()->for($this->hospital)->create(['slug' => 'emergencia-habil']);
    $afterHoursType = AdmissionType::factory()->for($this->hospital)->create(['slug' => 'emergencia-inhabil']);

    $resolved = app(BusinessHoursService::class)->resolveAdmissionType($baseType, $datetime);

    expect($resolved)->not->toBeNull();
    expect($resolved->id)->toBe($afterHoursType->id);
});

it('returns null when target slug does not exist', function () {
    $datetime = Carbon::parse('2025-09-15 10:00:00');
    setBusinessHours($this->hospital, 0, '07:00', '18:00');

    $baseType = AdmissionType::factory()->for($this->hospital)->create([
        'business_hour_type_slug' => 'no-existe',
    ]);

    $resolved = app(BusinessHoursService::class)->resolveAdmissionType($baseType, $datetime);

    expect($resolved)->toBeNull();
});
