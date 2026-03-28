<?php

namespace App\Http\Controllers;

// Base controller
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\JsonResponse;
use Throwable;

class HealthCheckController extends Controller
{
    public function apiCheck(): JsonResponse
    {
        // This controller action should only be reachable if config('app.self_hosted') is true
        // due to the routing configuration.

        $checks = [
            'database' => false,
            'redis' => false,
        ];
        $overallStatus = true;

        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
        } catch (Throwable $e) {
            Log::error('Health check: Database connection failed', ['exception' => $e->getMessage()]);
            $overallStatus = false;
        }

        try {
            Redis::ping();
            $checks['redis'] = true;
        } catch (Throwable $e) {
            Log::error('Health check: Redis connection failed', ['exception' => $e->getMessage()]);
            $overallStatus = false;
        }

        if ($overallStatus) {
            return response()->json([
                'status' => 'ok',
                'dependencies' => $checks,
            ]);
        }

        return response()->json([
            'status' => 'error',
            'dependencies' => $checks,
        ], 503);
    }
}
