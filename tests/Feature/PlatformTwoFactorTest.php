<?php

use App\Models\Hospital;
use App\Models\User;

test('a platform admin without confirmed two factor is redirected to the two-factor settings page', function () {
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $this->actingAs($admin)
        ->get(route('platform.dashboard'))
        ->assertRedirect(route('two-factor.show'));
});

test('a platform admin with confirmed two factor can reach the platform dashboard', function () {
    $admin = User::factory()->withTwoFactor()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $response = $this->actingAs($admin)
        ->get(route('platform.dashboard'))
        ->assertOk()
        // Regresión: el layout de plataforma debe envolver el contenido en <flux:main>
        // (data-flux-main), igual que el layout `app`. Sin esto, el contenido no
        // recibe el `grid-area: main` que define el sidebar y el navbar se ve roto.
        ->assertSee('data-flux-main', false)
        // Regresión: el sidebar de plataforma completo debe renderizarse, no el de
        // hospital reducido a un solo link. El `layout(): mixed` de la clase anónima de
        // Volt no es un mecanismo real de Livewire/Volt (solo funciona en componentes
        // funcionales vía `layout()` importado de Livewire\Volt) -- sin el atributo
        // `#[Layout(...)]` real, el componente caía silenciosamente al layout `app`.
        ->assertSee('Reportes operativos');

    $response->assertDontSee('Panel de Administrador');
});

test('the two-factor required message survives the redirect chain through password confirmation', function () {
    // Un admin de plataforma recién creado (p.ej. vía invitación) no tiene 2FA
    // confirmado ni la contraseña confirmada en sesión. La cadena real es:
    // /platform -> redirect a two-factor.show -> Fortify (password.confirm)
    // redirige de nuevo a la confirmación de contraseña. El mensaje debe
    // sobrevivir ambos saltos y verse en la página donde el usuario aterriza.
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $response = $this->actingAs($admin)
        ->followingRedirects()
        ->get(route('platform.dashboard'));

    $response->assertOk();
    $response->assertSee('Debes activar la verificación en dos pasos para acceder al panel de plataforma.');
});

test('a non platform admin still gets forbidden instead of the two-factor redirect', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false, 'role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('platform.dashboard'))
        ->assertForbidden();
});
