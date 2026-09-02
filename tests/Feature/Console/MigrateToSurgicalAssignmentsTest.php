<?php
// tests/Feature/Console/MigrateToSurgicalAssignmentsTest.php
use App\Models\Hospital;
use App\Models\PricingSetting;
use App\Models\RoleRate;
use App\Models\SurgicalAssignment;
use App\Models\SurgicalCase;
use App\Models\SurgicalRole;
use App\Models\User;

test('migra procedures legacy a surgical assignments y siembra roles/tarifas', function () {
    $hospital = Hospital::factory()->create();
    $instrumentist = User::factory()->create(['hospital_id' => $hospital->id]);
    $doctor = User::factory()->create(['hospital_id' => $hospital->id]);

    PricingSetting::withoutGlobalScopes()->create([
        'hospital_id' => $hospital->id,
        'default_rate' => 200, 'video_rate' => 300, 'night_rate' => 350, 'long_case_rate' => 350,
        'long_case_threshold_minutes' => 120, 'night_start' => '22:00', 'night_end' => '06:00',
    ]);

    $case = SurgicalCase::withoutGlobalScopes()->create([
        'hospital_id' => $hospital->id,
        'procedure_date' => '2026-08-01', 'start_time' => '10:00', 'end_time' => '11:00', 'duration_minutes' => 60,
        'patient_name' => 'Paciente Test', 'procedure_type' => 'Apendicectomía', 'is_videosurgery' => false,
        'instrumentist_id' => $instrumentist->id, 'instrumentist_name' => $instrumentist->name,
        'doctor_id' => $doctor->id, 'doctor_name' => $doctor->name,
        'circulating_id' => null, 'circulating_name' => 'Circulante Suelto',
        'calculated_amount' => 200, 'pricing_snapshot' => ['rule' => 'default_rate'], 'status' => 'pending',
    ]);

    $this->artisan('xacare:migrate-to-surgical-assignments')->assertExitCode(0);

    $roles = SurgicalRole::withoutGlobalScopes()->where('hospital_id', $hospital->id)->pluck('name')->sort()->values();
    expect($roles->all())->toBe(['Circulante', 'Cirujano', 'Instrumentista']);

    $defaultInstrumentistRate = RoleRate::withoutGlobalScopes()
        ->whereHas('surgicalRole', fn ($q) => $q->where('name', 'Instrumentista')->where('hospital_id', $hospital->id))
        ->whereNull('user_id')->whereNull('procedure_type')->first();
    expect((float) $defaultInstrumentistRate->base_rate)->toBe(200.0)
        ->and($defaultInstrumentistRate->modifiers)->toHaveCount(3);

    $assignments = SurgicalAssignment::withoutGlobalScopes()->where('surgical_case_id', $case->id)->get()->keyBy(fn ($a) => $a->surgicalRole->name);

    expect($assignments)->toHaveCount(3)
        ->and((float) $assignments['Instrumentista']->calculated_amount)->toBe(200.0)
        ->and($assignments['Instrumentista']->status)->toBe('pending')
        ->and($assignments['Instrumentista']->user_id)->toBe($instrumentist->id)
        ->and((float) $assignments['Cirujano']->calculated_amount)->toBe(0.0)
        ->and($assignments['Cirujano']->status)->toBe('paid')
        ->and($assignments['Cirujano']->user_id)->toBe($doctor->id)
        ->and((float) $assignments['Circulante']->calculated_amount)->toBe(0.0)
        ->and($assignments['Circulante']->user_id)->toBeNull();
});

test('el comando es idempotente', function () {
    $hospital = Hospital::factory()->create();
    SurgicalCase::withoutGlobalScopes()->create([
        'hospital_id' => $hospital->id,
        'procedure_date' => '2026-08-01', 'start_time' => '10:00', 'end_time' => '11:00', 'duration_minutes' => 60,
        'patient_name' => 'X', 'procedure_type' => 'Y', 'is_videosurgery' => false,
        'instrumentist_id' => null, 'doctor_id' => null, 'circulating_id' => null,
        'calculated_amount' => 0, 'status' => 'pending',
    ]);

    $this->artisan('xacare:migrate-to-surgical-assignments')->assertExitCode(0);
    $countAfterFirst = SurgicalRole::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count();

    $this->artisan('xacare:migrate-to-surgical-assignments')->assertExitCode(0);
    $countAfterSecond = SurgicalRole::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count();

    expect($countAfterFirst)->toBe(3)->and($countAfterSecond)->toBe(3);
});
