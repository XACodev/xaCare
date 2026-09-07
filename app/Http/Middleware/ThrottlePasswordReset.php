<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica el limiter `reset-password` (definido en FortifyServiceProvider) solo a
 * las rutas de reset de password de Fortify (`password.email`, `password.update`).
 *
 * Fortify no trae throttle de fábrica para estas rutas: su registro de rutas
 * (vendor/laravel/fortify/routes/routes.php) solo consulta
 * config('fortify.limiters.*') para 'login', 'two-factor' y 'verification'.
 * Este middleware se agrega al grupo `fortify.middleware` (config/fortify.php),
 * por lo que corre en todas las rutas de Fortify, pero solo actúa en las de
 * reset de password; para el resto es un no-op.
 */
class ThrottlePasswordReset
{
    private const THROTTLED_ROUTES = ['password.email', 'password.update'];

    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->route()?->getName(), self::THROTTLED_ROUTES, true)) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, 'reset-password');
    }
}
