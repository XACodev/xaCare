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

test('normaliza el nombre a title case al guardar', function () {
    $hospital = Hospital::factory()->create();
    $type = ProcedureType::factory()->for($hospital, 'hospital')->create(['name' => 'APENDICECTOMIA DE URGENCIA']);

    expect($type->name)->toBe('Apendicectomia de Urgencia');
});

test('resolveOrCreateFor reutiliza un tipo existente ignorando acentos y mayusculas', function () {
    // Finding 2 de la revision final: SQLite no tiene collation con acent-folding, así
    // que "Cesarea" y "Cesárea" quedaban como dos ProcedureType distintos -- fragmentando
    // el catálogo y haciendo que una tarifa configurada contra una grafía no aplicara a
    // la otra.
    $hospital = Hospital::factory()->create();
    $existing = ProcedureType::factory()->for($hospital, 'hospital')->create(['name' => 'Cesárea']);

    $resolved = ProcedureType::resolveOrCreateFor($hospital->id, 'cesarea');

    expect($resolved->id)->toBe($existing->id);
    expect(ProcedureType::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count())->toBe(1);
});

test('resolveOrCreateFor crea el tipo si no existe ninguna variante equivalente', function () {
    $hospital = Hospital::factory()->create();

    $created = ProcedureType::resolveOrCreateFor($hospital->id, 'Colecistectomía');

    expect($created->exists)->toBeTrue();
    expect($created->hospital_id)->toBe($hospital->id);
});

test('resolveOrCreateFor esta acotado por hospital: no reutiliza un tipo de otro hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();
    ProcedureType::factory()->for($hospitalA, 'hospital')->create(['name' => 'Cesárea']);

    $resolved = ProcedureType::resolveOrCreateFor($hospitalB->id, 'Cesarea');

    expect($resolved->hospital_id)->toBe($hospitalB->id);
    expect(ProcedureType::withoutGlobalScopes()->where('hospital_id', $hospitalB->id)->count())->toBe(1);
});

test('findByNameFor encuentra por nombre insensible a acentos sin crear nada', function () {
    // Finding 3: el preview de monto necesita esta variante sin efectos secundarios --
    // no debe crear un ProcedureType solo por calcular un preview.
    $hospital = Hospital::factory()->create();
    $existing = ProcedureType::factory()->for($hospital, 'hospital')->create(['name' => 'Cesárea']);

    $found = ProcedureType::findByNameFor($hospital->id, 'cesarea');
    expect($found?->id)->toBe($existing->id);

    $notFound = ProcedureType::findByNameFor($hospital->id, 'Apendicectomia Inexistente');
    expect($notFound)->toBeNull();
    expect(ProcedureType::withoutGlobalScopes()->where('hospital_id', $hospital->id)->count())->toBe(1);
});
