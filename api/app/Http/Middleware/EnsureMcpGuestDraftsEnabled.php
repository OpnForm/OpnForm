<?php

namespace App\Http\Middleware;

use App\Support\Mcp\McpAvailability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMcpGuestDraftsEnabled
{
    public function __construct(private readonly McpAvailability $availability)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->availability->guestDraftsEnabled(), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
