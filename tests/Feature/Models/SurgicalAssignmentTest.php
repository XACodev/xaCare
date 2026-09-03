<?php
// tests/Feature/Models/SurgicalAssignmentTest.php
use App\Models\Hospital;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\SurgicalRole;
use App\Models\User;

test('a surgical case can have multiple assignments across roles', function () {
    $hospital = Hospital::factory()->create();
    $this->actingAs(User::factory()->create(['hospital_id' => $hospital->id]));

    $case = SurgicalCase::factory()->create(['hospital_id' => $hospital->id]);
    $surgeonRole = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Cirujano']);
    $instrumentistRole = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Instrumentista']);

    SurgicalAssignment::factory()->for($case, 'surgicalCase')->for($surgeonRole, 'surgicalRole')->create(['calculated_amount' => 2000]);
    SurgicalAssignment::factory()->for($case, 'surgicalCase')->for($instrumentistRole, 'surgicalRole')->create(['calculated_amount' => 250]);

    expect($case->assignments)->toHaveCount(2)
        ->and($case->assignments->sum('calculated_amount'))->toEqual(2250.0);
});

test('courtesy assignment keeps amount at zero', function () {
    $hospital = Hospital::factory()->create();
    $this->actingAs(User::factory()->create(['hospital_id' => $hospital->id]));

    $assignment = SurgicalAssignment::factory()->create([
        'hospital_id' => $hospital->id,
        'is_courtesy' => true,
        'calculated_amount' => 0,
        'note' => 'Caso de beneficencia',
    ]);

    expect($assignment->is_courtesy)->toBeTrue()
        ->and((float) $assignment->calculated_amount)->toBe(0.0)
        ->and($assignment->note)->toBe('Caso de beneficencia');
});
