<?php

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

// Auditoría de Task 9: enumera TODAS las rutas nombradas `platform.*` (vía
// Route::getRoutes(), no una lista a mano) y confirma que cada una está
// realmente protegida por el grupo de middleware ['auth', 'platform-admin',
// 'platform-2fa']. Se excluye `platform.admin-invitations.accept`, que es
// intencionalmente pública (invitación de admin de plataforma sin cuenta).

function platformRoutesUnderTest(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => $route->getName() !== null
            && str_starts_with($route->getName(), 'platform.')
            && $route->getName() !== 'platform.admin-invitations.accept')
        ->values()
        ->all();
}

function resolvePlatformRouteUri(RoutingRoute $route, Hospital $hospital): string
{
    $uri = $route->uri();

    foreach ($route->parameterNames() as $parameter) {
        // Única ruta de plataforma con parámetro de ruta hoy: {hospital}.
        $value = $parameter === 'hospital' ? $hospital->id : 1;
        $uri = str_replace('{'.$parameter.'}', (string) $value, $uri);
    }

    return '/'.ltrim($uri, '/');
}

test('la auditoría cubre todas las rutas platform.* esperadas', function () {
    $names = collect(platformRoutesUnderTest())->map->getName()->sort()->values()->all();

    expect($names)->toBe([
        'platform.activity.index',
        'platform.admins.index',
        'platform.dashboard',
        'platform.hospitals.create',
        'platform.hospitals.edit',
        'platform.hospitals.index',
        'platform.permissions.index',
        'platform.roles.index',
    ]);
});

test('un usuario autenticado que no es admin de plataforma recibe 403 en cada ruta platform.*', function () {
    $hospital = Hospital::factory()->create();
    $nonPlatformAdmin = User::factory()->create([
        'hospital_id' => $hospital->id,
        'is_platform_admin' => false,
        'role' => 'admin',
    ]);

    $this->actingAs($nonPlatformAdmin);

    foreach (platformRoutesUnderTest() as $route) {
        $uri = resolvePlatformRouteUri($route, $hospital);

        $this->get($uri)->assertForbidden();
    }
});

test('un usuario no autenticado es redirigido a login en cada ruta platform.*', function () {
    $hospital = Hospital::factory()->create();

    foreach (platformRoutesUnderTest() as $route) {
        $uri = resolvePlatformRouteUri($route, $hospital);

        $this->get($uri)->assertRedirect(route('login'));
    }
});
