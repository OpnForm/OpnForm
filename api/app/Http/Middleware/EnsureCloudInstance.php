<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCloudInstance
{
    /**
     * Allow requests from cloud instances only.
     */
    public function handle(Request $request, Closure $next)
    {
        if (config('app.self_hosted')) {
            return response()->json(['error' => 'Only available on cloud instances.'], 404);
        }

        return $next($request);
    }
}
