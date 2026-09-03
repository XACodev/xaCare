<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHospitalSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->is_platform_admin || ! $user->hospital_id) {
            return $next($request);
        }

        $hospital = $user->hospital;

        abort_unless($hospital?->subscriptionAllowsAccess() ?? false, 403, 'La suscripción de este hospital no está activa.');

        return $next($request);
    }
}
