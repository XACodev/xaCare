<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertStatus(200);
});

test('admins see admin shortcuts and stats on dashboard', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    // Create some data
    \App\Models\SurgicalCase::factory()->create(['status' => 'pending']);
    \App\Models\PayoutBatch::factory()->create(['total_amount' => 500, 'status' => 'active']);

    $response = $this->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('Procedimientos');
    $response->assertSee('Pagos');
    $response->assertSee('Total Procedimientos');
    $response->assertSee('Pendientes de Pago');
    $response->assertSee('Total Pagado');
});

test('instrumentists see instrumentist shortcuts and stats on dashboard', function () {
    $user = User::factory()->create(['role' => 'instrumentist']);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('Mis Pagos');
    $response->assertSee('Mi Perfil');
    $response->assertSee('Ganancias Totales');
    $response->assertSee('Pendiente de Cobro');
});

test('other users see default shortcuts on dashboard', function () {
    $user = User::factory()->create(['role' => 'doctor']);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('Mi Perfil');
    $response->assertDontSee('Ajustar precios y configuraciones.');
    $response->assertDontSee('Ver mi historial de pagos recibidos.');
});