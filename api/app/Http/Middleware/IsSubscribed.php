<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsSubscribed
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && !$request->user()->hasActiveDefaultSubscription()) {
            // This user is not a paying customer...
            if ($request->expectsJson()) {
                return response([
                    'message' => 'You are not subscribed to NotionForms Pro.',
                    'type' => 'error',
                ], 401);
            }

            return redirect('billing');
        }

        return $next($request);
    }
}
