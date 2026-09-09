<?php

use Illuminate\Support\Facades\Route;

// Regresión: todo nombre de ruta usado con route(...) en los sidebars debe existir
// registrado. Antes de esta pasada, un route() apuntando a una ruta inexistente
// dentro de un sidebar habría lanzado RouteNotFoundException al renderizar, en vez
// de un 404 detectable en tests de feature normales.
function routeNamesUsedIn(string $bladePath): array
{
    $contents = file_get_contents($bladePath);

    preg_match_all('/route\(\s*[\'"]([a-zA-Z0-9_.\-]+)[\'"]/', $contents, $matches);

    return array_unique($matches[1]);
}

test('todos los route() usados en el sidebar de hospital existen', function () {
    $names = routeNamesUsedIn(resource_path('views/components/layouts/app/sidebar.blade.php'));

    expect($names)->not->toBeEmpty();

    foreach ($names as $name) {
        expect(Route::has($name))->toBeTrue("La ruta '{$name}' usada en el sidebar de hospital no existe.");
    }
});

test('todos los route() usados en el sidebar de plataforma existen', function () {
    $names = routeNamesUsedIn(resource_path('views/components/layouts/platform/sidebar.blade.php'));

    expect($names)->not->toBeEmpty();

    foreach ($names as $name) {
        expect(Route::has($name))->toBeTrue("La ruta '{$name}' usada en el sidebar de plataforma no existe.");
    }
});
