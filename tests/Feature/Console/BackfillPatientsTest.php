<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Models\User;

test('backfill assigns hospital and creates patients from procedure names', function () {
    $hospital = Hospital::factory()->create(['slug' => 'hnsc']);

    $user = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'instrumentist']);

    foreach (['Ana Gomez', 'Ana Gomez', 'Luis Perez'] as $name) {
        SurgicalCase::withoutGlobalScopes()->create([
            'procedure_date' => now()->toDateString(),
            'start_time' => '08:00', 'end_time' => '09:00',
            'patient_name' => $name, 'procedure_type' => 'X',
            'status' => 'pending',
        ]);
    }

    $this->artisan('xacare:backfill-patients --hospital=hnsc')->assertSuccessful();

    expect(Patient::withoutGlobalScopes()->count())->toBe(2);
    expect($user->fresh()->hospital_id)->toBe($hospital->id);

    $anas = SurgicalCase::withoutGlobalScopes()->where('patient_name', 'Ana Gomez')->pluck('patient_id')->unique();
    expect($anas->count())->toBe(1);
    expect($anas->first())->not->toBeNull();
});

test('backfill-patients does not mix patient names across hospitals', function () {
    $hospitalA = Hospital::factory()->create(['slug' => 'hnsc']);
    $hospitalB = Hospital::factory()->create(['slug' => 'other-hospital']);

    $caseA = SurgicalCase::withoutGlobalScopes()->create([
        'hospital_id' => $hospitalA->id,
        'procedure_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '09:00',
        'duration_minutes' => 60,
        'patient_name' => 'Maria Lopez',
        'procedure_type' => 'Apendicectomia',
        'status' => 'pending',
        'calculated_amount' => 0,
    ]);

    $caseB = SurgicalCase::withoutGlobalScopes()->create([
        'hospital_id' => $hospitalB->id,
        'procedure_date' => now()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'duration_minutes' => 60,
        'patient_name' => 'Maria Lopez',
        'procedure_type' => 'Colecistectomia',
        'status' => 'pending',
        'calculated_amount' => 0,
    ]);

    $this->artisan('xacare:backfill-patients')->assertExitCode(0);

    $caseA->refresh();
    $caseB->refresh();

    expect($caseA->patient_id)->not->toBeNull();
    expect($caseB->patient_id)->not->toBeNull();

    $patientA = Patient::withoutGlobalScopes()->findOrFail($caseA->patient_id);
    $patientB = Patient::withoutGlobalScopes()->findOrFail($caseB->patient_id);

    expect($patientA->hospital_id)->toBe($hospitalA->id);
    expect($patientB->hospital_id)->toBe($hospitalB->id);
    expect($patientA->id)->not->toBe($patientB->id);
});
