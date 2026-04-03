<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSelfHostedInstance
{
    /**
     * Allow requests from self-hosted instances only.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!config('app.self_hosted')) {
            return response()->json(['error' => 'Only available on self-hosted instances.'], 404);
        }

        return $next($request);
    }
}
