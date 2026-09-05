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

        if ($hospital?->subscriptionAllowsAccess() ?? false) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'La suscripción de este hospital no está activa.');
        }

        return redirect()->route('billing.suspended');
    }
}
