<?php

namespace App\Http\Middleware;

use App\Service\OAuth\McpOAuthSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConsumeMcpOAuthLoginTicket
{
    public function __construct(private readonly McpOAuthSessionService $sessions)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('passport.authorizations.authorize') && $request->filled('mcp_login_ticket')) {
            $this->sessions->consumeLoginTicket(
                (string) $request->query('mcp_login_ticket'),
                $request
            );

            return redirect($this->sessions->withoutLoginTicket($request));
        }

        return $next($request);
    }
}
