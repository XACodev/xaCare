<?php

use Illuminate\Support\Facades\Route;

test('every route that requires auth also runs inside the web middleware group', function () {
    $offenders = [];

    foreach (Route::getRoutes() as $route) {
        $middleware = $route->middleware();

        if (! in_array('auth', $middleware, true)) {
            continue;
        }

        if (! in_array('web', $middleware, true)) {
            $offenders[] = $route->uri();
        }
    }

    expect($offenders)->toBe([]);
});
