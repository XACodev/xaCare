<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorForPlatformAdmins
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->is_platform_admin
            && $user->two_factor_confirmed_at === null
            && ! $request->routeIs('two-factor.show')
            && ! $request->routeIs('logout')
        ) {
            // `flash()` solo sobrevive a la SIGUIENTE petición. Cuando `password.confirm`
            // está activo (config/fortify.php), esta redirección es seguida por otra
            // redirección de Fortify hacia la confirmación de contraseña antes de que
            // la vista de 2FA se renderice: son dos saltos, y flash() se pierde en el
            // segundo. `put()` persiste hasta que la página que finalmente la muestra
            // la limpia explícitamente (ver settings/two-factor.blade.php).
            session()->put('status', 'Debes activar la verificación en dos pasos para acceder al panel de plataforma.');

            return redirect()->route('two-factor.show');
        }

        return $next($request);
    }
}
