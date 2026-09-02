<?php
// tests/Feature/Models/ActivityLoggingTest.php
use App\Models\Hospital;
use App\Models\SurgicalAssignment;
use App\Models\User;

test('cambiar el monto de una asignacion registra quien lo hizo', function () {
    $hospital = Hospital::factory()->create();
    $editor = User::factory()->create(['hospital_id' => $hospital->id, 'name' => 'Dra. Ana']);
    $this->actingAs($editor);

    $assignment = SurgicalAssignment::factory()->create([
        'hospital_id' => $hospital->id,
        'calculated_amount' => 2000,
    ]);

    $assignment->update(['calculated_amount' => 2200, 'note' => '+Q200 por complicacion']);

    // Se ordena tambien por id: en sqlite el `created_at` de las actividades
    // "created" y "updated" puede caer en el mismo segundo, y latest() por si
    // solo (order by created_at) no desempata de forma determinista.
    $activity = $assignment->activities()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($editor->id)
        ->and((float) $activity->properties['attributes']['calculated_amount'])->toBe(2200.0)
        ->and((float) $activity->properties['old']['calculated_amount'])->toBe(2000.0);
});
