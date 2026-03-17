<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticateJWT
{
    public const API_SERVER_SECRET_HEADER_NAME = 'x-api-secret';

    /**
     * Verifies the JWT token and validates the User Agent.
     * Invalidates token otherwise.
     */
    public function handle(Request $request, Closure $next)
    {
        // If skipping JWT client fingerprint validation is enabled in config, skip the rest
        if (config('app.jwt_skip_ip_ua_validation')) {
            return $next($request);
        }

        // Parse JWT Payload
        try {
            $payload = JWTAuth::parseToken()->getPayload();
        } catch (JWTException $e) {
            return $next($request);
        }

        // Validate User Agent
        if ($payload) {
            if ($frontApiSecret = $request->header(self::API_SERVER_SECRET_HEADER_NAME)) {
                // If it's a trusted SSR request, skip the rest
                if ($frontApiSecret === config('app.front_api_secret')) {
                    return $next($request);
                }
            }

            // If it's impersonating, skip the rest
            if ($payload->get('impersonating')) {
                return $next($request);
            }

            $error = null;
            if (! Hash::check($request->userAgent(), $payload->get('ua'))) {
                $error = 'Origin User Agent is invalid';
            }

            if ($error) {
                auth()->invalidate();

                return response()->json([
                    'message' => $error,
                ], 401);
            }
        }

        return $next($request);
    }
}
