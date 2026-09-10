<?php

use App\Models\Hospital;
use App\Modules\QxLog\Models\ProcedureType;
use App\Models\User;

test('un tipo de cirugia pertenece a un hospital y se autoasigna slug', function () {
    $hospital = Hospital::factory()->create();
    $type = ProcedureType::factory()->for($hospital, 'hospital')->create(['name' => 'Apendicectomía']);

    expect($type->hospital_id)->toBe($hospital->id)
        ->and($type->slug)->not->toBeNull()
        ->and(strlen($type->slug))->toBe(12)
        ->and($type->active)->toBeTrue();
});

test('un admin no ve tipos de cirugia de otro hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    ProcedureType::factory()->for($hospitalA, 'hospital')->create(['name' => 'Cesárea']);
    ProcedureType::factory()->for($hospitalB, 'hospital')->create(['name' => 'Colecistectomía']);

    $admin = User::factory()->create(['hospital_id' => $hospitalA->id]);
    $this->actingAs($admin);

    expect(ProcedureType::all())->toHaveCount(1);
});
