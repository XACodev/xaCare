<?php
// tests/Feature/Livewire/Procedures/ProcedureTypeSearchTest.php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\ProcedureType;
use Livewire\Volt\Volt;

test('el buscador de tipo de cirugia solo sugiere tipos del hospital del usuario que matchean el texto', function () {
    $hospital = Hospital::factory()->create();
    $otherHospital = Hospital::factory()->create();
    $user = User::factory()->create(['role' => 'instrumentist', 'hospital_id' => $hospital->id]);

    ProcedureType::factory()->for($hospital, 'hospital')->create(['name' => 'Cesárea']);
    ProcedureType::factory()->for($hospital, 'hospital')->create(['name' => 'Colecistectomía']);
    ProcedureType::factory()->for($otherHospital, 'hospital')->create(['name' => 'Cesárea de otro hospital']);

    $this->actingAs($user);

    $component = Volt::test('qxlog.procedures.create')->set('procedure_type_query', 'ces');

    expect($component->instance()->procedureTypeSuggestions)->toHaveCount(1)
        ->and($component->instance()->procedureTypeSuggestions->first()->name)->toBe('Cesárea');
});

test('seleccionar una sugerencia fija procedure_type_id y el texto del nombre', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['role' => 'instrumentist', 'hospital_id' => $hospital->id]);
    $type = ProcedureType::factory()->for($hospital, 'hospital')->create(['name' => 'Cesárea']);

    $this->actingAs($user);

    Volt::test('qxlog.procedures.create')
        ->set('procedure_type_query', 'ces')
        ->call('selectProcedureType', $type->id)
        ->assertSet('procedure_type_id', $type->id)
        ->assertSet('procedure_type_query', 'Cesárea');
});
