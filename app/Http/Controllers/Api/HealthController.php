<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * System health check endpoint.
     *
     * GET /api/health
     *
     * Checks:
     *  1. Laravel is running
     *  2. Main application DB is reachable
     */
    public function check(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (\Throwable) {
            $dbStatus = 'disconnected';
        }

        $isHealthy = $dbStatus === 'connected';

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'db' => $dbStatus,
            'timestamp' => now()->toIso8601String(),
            'version' => app()->version(),
        ], $isHealthy ? 200 : 503);
    }
}
