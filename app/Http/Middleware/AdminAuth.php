<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // El super admin no siempre tiene el role Spatie "admin" asignado (solo el flag
        // is_platform_admin) — sin este OR, el middleware lo bloqueaba antes de llegar al
        // mount() de cada componente, aunque ese componente ya supiera tratarlo como
        // solo-lectura (abort_if is_platform_admin en las paginas de escritura, o
        // is_platform_admin permitido en las de lectura). Ver PR de fix de permisos.
        abort_unless((bool) $user && ($user->hasRole('admin') || $user->is_platform_admin), 401);

        return $next($request);
    }
}
