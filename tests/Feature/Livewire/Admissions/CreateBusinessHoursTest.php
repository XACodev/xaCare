<?php

use App\Models\Admission;
use App\Models\AdmissionType;
use App\Models\BusinessHoliday;
use App\Models\BusinessHour;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

function createAdmin(Hospital $hospital): User
{
    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $user->assignRole('admin');

    return $user;
}

function seedTypesWithMapping(Hospital $hospital): array
{
    \App\Support\AdmissionTypeSeeder::seedDefaultsFor($hospital);

    $habil = AdmissionType::where('hospital_id', $hospital->id)->where('slug', 'emergencia-habil')->firstOrFail();
    $inhabil = AdmissionType::where('hospital_id', $hospital->id)->where('slug', 'emergencia-inhabil')->firstOrFail();

    $base = AdmissionType::factory()->for($hospital)->create([
        'name' => 'Emergencia',
        'slug' => 'emergencia',
        'business_hour_type_slug' => 'emergencia-habil',
        'after_hours_type_slug' => 'emergencia-inhabil',
        'sort_order' => 99,
    ]);

    return [
        'base' => $base,
        'habil' => $habil,
        'inhabil' => $inhabil,
    ];
}

it('shows only base types when admissions_business_hours addon is active', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_business_hours']]);
    $user = createAdmin($hospital);
    $types = seedTypesWithMapping($hospital);

    Volt::actingAs($user)->test('admissions.create')
        ->assertSeeText($types['base']->name)
        ->assertDontSeeText($types['habil']->name)
        ->assertDontSeeText($types['inhabil']->name);
});

it('shows all variants when admissions_business_hours addon is not active', function () {
    $hospital = Hospital::factory()->create();
    $user = createAdmin($hospital);
    $types = seedTypesWithMapping($hospital);

    Volt::actingAs($user)->test('admissions.create')
        ->assertSeeText($types['base']->name)
        ->assertSeeText($types['habil']->name)
        ->assertSeeText($types['inhabil']->name);
});

it('saves business hour mapped type during business hours', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_business_hours']]);
    $user = createAdmin($hospital);
    $types = seedTypesWithMapping($hospital);

    // Lunes 15 de septiembre de 2025 a las 10:00
    BusinessHour::updateOrCreate(
        ['hospital_id' => $hospital->id, 'day_of_week' => 0],
        ['start_time' => '07:00', 'end_time' => '18:00', 'is_business_day' => true]
    );

    Volt::actingAs($user)->test('admissions.create')
        ->call('newPatient')
        ->set('p_primer_apellido', 'Perez')
        ->set('p_primer_nombre', 'Luis')
        ->call('nextStep')
        ->call('nextStep')
        ->set('admissionTypeId', $types['base']->id)
        ->set('a_fecha_ingreso', '2025-09-15')
        ->set('a_hora_ingreso', '10:00')
        ->set('a_sala_ingreso', 'Emergencia')
        ->call('save')
        ->assertHasNoErrors();

    $admission = Admission::first();
    expect($admission)->not->toBeNull();
    expect($admission->admission_type_id)->toBe($types['habil']->id);
});

it('saves after hours mapped type outside business hours', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_business_hours']]);
    $user = createAdmin($hospital);
    $types = seedTypesWithMapping($hospital);

    BusinessHour::updateOrCreate(
        ['hospital_id' => $hospital->id, 'day_of_week' => 0],
        ['start_time' => '07:00', 'end_time' => '18:00', 'is_business_day' => true]
    );

    Volt::actingAs($user)->test('admissions.create')
        ->call('newPatient')
        ->set('p_primer_apellido', 'Perez')
        ->set('p_primer_nombre', 'Luis')
        ->call('nextStep')
        ->call('nextStep')
        ->set('admissionTypeId', $types['base']->id)
        ->set('a_fecha_ingreso', '2025-09-15')
        ->set('a_hora_ingreso', '20:00')
        ->set('a_sala_ingreso', 'Emergencia')
        ->call('save')
        ->assertHasNoErrors();

    $admission = Admission::first();
    expect($admission)->not->toBeNull();
    expect($admission->admission_type_id)->toBe($types['inhabil']->id);
});

it('saves after hours mapped type on a holiday', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_business_hours']]);
    $user = createAdmin($hospital);
    $types = seedTypesWithMapping($hospital);

    BusinessHour::updateOrCreate(
        ['hospital_id' => $hospital->id, 'day_of_week' => 0],
        ['start_time' => '07:00', 'end_time' => '18:00', 'is_business_day' => true]
    );

    BusinessHoliday::create([
        'hospital_id' => $hospital->id,
        'date' => '2025-09-15',
        'name' => 'Feriado',
    ]);

    Volt::actingAs($user)->test('admissions.create')
        ->call('newPatient')
        ->set('p_primer_apellido', 'Perez')
        ->set('p_primer_nombre', 'Luis')
        ->call('nextStep')
        ->call('nextStep')
        ->set('admissionTypeId', $types['base']->id)
        ->set('a_fecha_ingreso', '2025-09-15')
        ->set('a_hora_ingreso', '10:00')
        ->set('a_sala_ingreso', 'Emergencia')
        ->call('save')
        ->assertHasNoErrors();

    $admission = Admission::first();
    expect($admission)->not->toBeNull();
    expect($admission->admission_type_id)->toBe($types['inhabil']->id);
});

it('falls back to base type when target slug is missing', function () {
    $hospital = Hospital::factory()->create(['addons' => ['admissions_business_hours']]);
    $user = createAdmin($hospital);
    \App\Support\AdmissionTypeSeeder::seedDefaultsFor($hospital);

    $base = AdmissionType::where('hospital_id', $hospital->id)->where('slug', 'emergencia-habil')->firstOrFail();
    $base->update(['slug' => 'emergencia', 'business_hour_type_slug' => 'no-existe']);

    BusinessHour::updateOrCreate(
        ['hospital_id' => $hospital->id, 'day_of_week' => 0],
        ['start_time' => '07:00', 'end_time' => '18:00', 'is_business_day' => true]
    );

    Volt::actingAs($user)->test('admissions.create')
        ->call('newPatient')
        ->set('p_primer_apellido', 'Perez')
        ->set('p_primer_nombre', 'Luis')
        ->call('nextStep')
        ->call('nextStep')
        ->set('admissionTypeId', $base->id)
        ->set('a_fecha_ingreso', '2025-09-15')
        ->set('a_hora_ingreso', '10:00')
        ->set('a_sala_ingreso', 'Emergencia')
        ->call('save')
        ->assertHasNoErrors();

    $admission = Admission::first();
    expect($admission)->not->toBeNull();
    expect($admission->admission_type_id)->toBe($base->id);
});
