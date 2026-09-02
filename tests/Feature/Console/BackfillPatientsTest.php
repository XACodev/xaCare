<?php

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\SurgicalCase;
use App\Models\User;

test('backfill assigns hospital and creates patients from procedure names', function () {
    $hospital = Hospital::factory()->create(['slug' => 'hnsc']);

    $user = User::factory()->create(['hospital_id' => null, 'role' => 'instrumentist']);

    // Two procedures with the same patient name, one different.
    foreach (['Ana Gomez', 'Ana Gomez', 'Luis Perez'] as $name) {
        SurgicalCase::withoutGlobalScopes()->create([
            'procedure_date' => now()->toDateString(),
            'start_time' => '08:00', 'end_time' => '09:00',
            'patient_name' => $name, 'procedure_type' => 'X',
            'instrumentist_id' => $user->id, 'status' => 'pending',
        ]);
    }

    $this->artisan('xacare:backfill-patients --hospital=hnsc')->assertSuccessful();

    expect(Patient::withoutGlobalScopes()->count())->toBe(2);
    expect($user->fresh()->hospital_id)->toBe($hospital->id);

    $anas = SurgicalCase::withoutGlobalScopes()->where('patient_name', 'Ana Gomez')->pluck('patient_id')->unique();
    expect($anas->count())->toBe(1);
    expect($anas->first())->not->toBeNull();
});
