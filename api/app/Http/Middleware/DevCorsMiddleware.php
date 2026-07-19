<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DevCorsMiddleware
{
    private const DEFAULT_ALLOWED_HEADERS = [
        'DNT',
        'User-Agent',
        'X-Requested-With',
        'If-Modified-Since',
        'Cache-Control',
        'Content-Type',
        'Range',
        'Authorization',
        'X-XSRF-TOKEN',
        'Accept',
        'Precognition',
        'Precognition-Validate-Only',
    ];

    public function handle(Request $request, Closure $next)
    {
        // Only apply in development mode
        if (!config('app.dev_cors')) {
            return $next($request);
        }

        $response = $next($request);

        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 200);
        }

        $allowedOrigin = $request->headers->get('Origin')
            ?: config('app.front_url')
            ?: 'http://localhost:3000';
        $allowedHeaders = $request->headers->get('Access-Control-Request-Headers')
            ?: implode(', ', self::DEFAULT_ALLOWED_HEADERS);

        // Reflect the active frontend origin in development so local ports can vary.
        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin, true);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH', true);
        $response->headers->set('Access-Control-Allow-Headers', $allowedHeaders, true);
        $response->headers->set('Access-Control-Allow-Credentials', 'true', true);
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Length, Content-Range', true);
        $response->setVary([
            'Origin',
            'Access-Control-Request-Method',
            'Access-Control-Request-Headers',
        ], false);

        return $response;
    }
}
