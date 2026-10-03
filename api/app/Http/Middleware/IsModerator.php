<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsModerator
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $scope = null)
    {
        if ($request->is('external/admin/*')) {
            abort_if(config('app.self_hosted'), 404);
            $token = $request->user()?->currentAccessToken();
            abort_unless($request->bearerToken() && $token instanceof \Laravel\Sanctum\PersonalAccessToken
                && \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken())?->id === $token->id, 401);
            $target = $request->route('user');
            abort_if($target instanceof \App\Models\User && $target->admin, 403);
            $scope ??= $request->route()->defaults['admin_scope'] ?? null;
            abort_unless($request->user()->moderator && !$request->user()->is_blocked
                && $scope && in_array($scope, $token->abilities, true)
                && $token->expires_at?->isFuture(), 403);
        }
        if ($request->user() && ! $request->user()->moderator) {
            // This user is not a paying customer...
            if ($request->expectsJson()) {
                return response([
                    'message' => 'You are not allowed.',
                    'type' => 'error',
                ], 403);
            }

            return redirect('home');
        }

        return $next($request);
    }
}
