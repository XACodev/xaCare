<?php

use App\Models\Hospital;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

test('un instrumentista sin permisos de cirugia recibe 403 en el tablero', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $hospital = Hospital::factory()->create();
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'instrumentist',
    ]);

    $this->actingAs($user)
        ->get(route('surgeries.board'))
        ->assertForbidden();
});

test('un instrumentista sin permisos de cirugia recibe 403 al intentar agendar', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $hospital = Hospital::factory()->create();
    $user = User::factory()->create([
        'hospital_id' => $hospital->id,
        'role' => 'instrumentist',
    ]);

    $this->actingAs($user)
        ->get(route('surgeries.schedule.create'))
        ->assertForbidden();
});
