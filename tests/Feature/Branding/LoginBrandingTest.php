<?php

test('la pantalla de login usa el copy generico de marca, no el stub de Laravel ni el hospital del mockup', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Ingresos en minutos, expediente completo después.')
        ->assertSee('Pacientes, cirugías, pagos y seguros en un solo lugar')
        ->assertSee('Plataforma de gestión hospitalaria y clínica')
        ->assertDontSee('Hospital Nuestra Señora del Carmen')
        ->assertDontSee('Centro Médico y Hospital');
});

test('el panel de login ya no usa la cita aleatoria de Laravel', function () {
    $blade = file_get_contents(resource_path('views/components/layouts/auth/split.blade.php'));

    expect($blade)->not->toContain('Illuminate\Foundation\Inspiring');
});
