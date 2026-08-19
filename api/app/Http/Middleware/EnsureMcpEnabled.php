<?php

namespace App\Http\Middleware;

use App\Support\Mcp\McpAvailability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMcpEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(McpAvailability::class)->enabled(), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
