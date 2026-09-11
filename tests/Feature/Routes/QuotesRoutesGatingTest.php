<?php
// tests/Feature/Routes/QuotesRoutesGatingTest.php

use App\Models\Hospital;
use App\Models\User;

test('sin el feature qxlog_quotes, la ruta de cotizaciones responde 403', function () {
    $hospital = Hospital::factory()->create(['features' => ['qxlog']]);
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);

    $this->actingAs($admin)->get('/quotes')->assertForbidden();
});

test('con el feature activo, la ruta de cotizaciones existe (no es 404)', function () {
    $hospital = Hospital::factory()->create(['features' => ['qxlog', 'qxlog_quotes']]);
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);

    $response = $this->actingAs($admin)->get('/quotes');

    expect($response->status())->not->toBe(404);
});
