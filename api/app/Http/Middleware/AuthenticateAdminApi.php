<?php

namespace App\Http\Middleware;

use App\Service\AdminApi\Operations;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticateAdminApi
{
    public function handle(Request $request, Closure $next, ?string $scope = null)
    {
        $token = $request->user()?->currentAccessToken();
        $bearer = $request->bearerToken();
        abort_unless($bearer && $token instanceof PersonalAccessToken && PersonalAccessToken::findToken($bearer)?->id === $token->id, 401);
        abort_unless($request->user()->moderator && !$request->user()->is_blocked, 403);
        $abilities = $token->abilities ?? [];
        abort_unless(in_array(Operations::TOKEN_MARKER, $abilities, true), 403);
        // Explicit scopes only: legacy/customer '*' tokens never grant admin access.
        $scope ??= Operations::scope((string) ($request->route('operation') ?? $request->query('operation')));
        abort_unless(in_array($scope, $abilities, true), 403);
        abort_unless($token->expires_at && $token->expires_at->isFuture(), 403);
        return $next($request);
    }
}
