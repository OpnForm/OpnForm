<?php

namespace App\Http\Middleware;

use App\Service\OAuth\McpOAuthSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentMcpOAuthSession
{
    public function __construct(private readonly McpOAuthSessionService $sessions)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->sessions->ensureCurrentWebSession($request);

        return $next($request);
    }
}
