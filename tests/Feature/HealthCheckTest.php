<?php

test('el endpoint de health responde 200 con el estado de los servicios', function () {
    $response = $this->get(route('health'));

    $response->assertStatus(200)
        ->assertJsonStructure(['status', 'database', 'cache', 'timestamp'])
        ->assertJson([
            'status' => 'ok',
            'database' => 'ok',
            'cache' => 'ok',
        ]);

    $timestamp = $response->json('timestamp');

    expect(fn () => new DateTime($timestamp))->not->toThrow(Exception::class);
});
