<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireOAuthS256
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! hash_equals('S256', (string) $request->query('code_challenge_method'))) {
            return new JsonResponse([
                'error' => 'invalid_request',
                'error_description' => 'The code_challenge_method must be S256.',
            ], 400);
        }

        return $next($request);
    }
}
