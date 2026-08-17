<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateOptionalMcpOAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken()) {
            return $next($request);
        }

        $guard = Auth::guard('oauth');
        $user = $guard->user();

        if (! $user || ! $user->tokenCan('mcp:use')) {
            throw new AuthenticationException('Invalid or insufficient MCP access token.', ['oauth']);
        }

        if ($user->is_blocked) {
            return response()->json([
                'message' => 'Your account has been blocked. Please contact support.',
            ], 403);
        }

        Auth::shouldUse('oauth');
        Auth::setUser($user);

        return $next($request);
    }
}
