<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHospitalFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        abort_unless((bool) $user, 403);

        if ($user->is_platform_admin) {
            return $next($request);
        }

        abort_unless((bool) $user->hospital?->hasFeature($feature), 403);

        return $next($request);
    }
}
