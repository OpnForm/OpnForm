<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OptionalSanctumAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Support token via query string (for ChatGPT developer mode testing)
        if (!$request->bearerToken() && $request->query('token')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->query('token'));
        }

        if ($request->bearerToken()) {
            $bearerToken = $request->bearerToken();

            // Try Passport OAuth token first
            $passportUser = Auth::guard('passport')->user();

            if ($passportUser) {
                // Set user without an access token so policies treat this
                // as a fully authenticated session (Passport scopes already
                // authorized the request during the OAuth consent flow).
                Auth::setUser($passportUser->withAccessToken(null));

                return $next($request);
            }

            // Passport's TokenGuard clears the Authorization header on failure,
            // so restore it before trying Sanctum
            if (!$request->bearerToken() && $bearerToken) {
                $request->headers->set('Authorization', 'Bearer ' . $bearerToken);
            }

            // Fall back to Sanctum token
            if (Auth::guard('sanctum')->check()) {
                Auth::setUser(Auth::guard('sanctum')->user());
            }
        }

        return $next($request);
    }
}
