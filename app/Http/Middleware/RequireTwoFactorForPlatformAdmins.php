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
            session()->flash('status', 'Debes activar la verificación en dos pasos para acceder al panel de plataforma.');

            return redirect()->route('two-factor.show');
        }

        return $next($request);
    }
}
