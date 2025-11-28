<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthenticateWithJwtOrSanctum
{
    /**
     * Handle an incoming request by authenticating via JWT or Sanctum.
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Attempt authentication via the default 'api' guard (JWT)
        if (Auth::guard('api')->check()) {
            Auth::shouldUse('api');
            return $next($request);
        }

        // 2. Fallback to Sanctum guard
        if (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();

            Auth::setUser($user);

            // Validate route against whitelist
            $routeName = $request->route()?->getName();
            $allowedRoutes = config('sanctum-routes.allowed', []);

            if (! $routeName || ! in_array($routeName, $allowedRoutes, true)) {
                // Return 404 to avoid revealing route existence
                throw new NotFoundHttpException();
            }

            return $next($request);
        }

        // 3. Neither guard could authenticate
        throw new AuthenticationException();
    }
}
