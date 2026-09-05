<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(Request $request)
    {
        $healthy = true;

        try {
            DB::select('SELECT 1');
            $database = 'ok';
        } catch (Throwable $e) {
            $database = 'error';
            $healthy = false;
            Log::channel('saas')->error('Health check: database no disponible');
        }

        try {
            Cache::put('health-check', true, 5);
            $cache = Cache::get('health-check') === true ? 'ok' : 'error';
        } catch (Throwable $e) {
            $cache = 'error';
        }

        if ($cache === 'error') {
            $healthy = false;
            Log::channel('saas')->error('Health check: cache no disponible');
        }

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'database' => $database,
            'cache' => $cache,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }
}
