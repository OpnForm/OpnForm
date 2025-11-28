<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;

class ImpersonationMiddleware
{
    public const ADMIN_LOG_PREFIX = '[admin_action] ';

    public const LOG_ROUTES = [
        'open.forms.store',
        'open.forms.update',
        'open.forms.duplicate',
        'open.forms.regenerate-link',
    ];

    public const ALLOWED_ROUTES = [
        'logout',

        // Forms
        'forms.ai.generate',
        'forms.ai.show',
        'forms.assets.show',
        'forms.show',
        'forms.answer',
        'forms.fetchSubmission',
        'forms.users.index',
        'open.forms.index-all',
        'open.forms.store',
        'open.forms.assets.upload',
        'open.forms.update',
        'open.forms.duplicate',
        'open.forms.regenerate-link',
        'open.forms.submissions',
        'open.forms.submissions.file',
        'open.providers',
        'open.forms.integrations.index',
        'open.forms.integrations.events',

        // Workspaces
        'open.workspaces.index',
        'open.workspaces.create',
        'open.workspaces.delete',
        'open.workspaces.save-custom-domains',
        'open.workspaces.databases.search',
        'open.workspaces.databases.show',
        'open.workspaces.form.stats',
        'open.workspaces.forms.index',
        'open.workspaces.users.index',

        'templates.index',
        'templates.create',
        'templates.update',
        'templates.show',

        'user.current',
        'local.temp',
        'vapor.signed-storage-url',
        'upload-file'
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            if (!Auth::guard('api')->check() || !Auth::guard('api')->payload()->get('impersonating')) {
                return $next($request);
            }
        } catch (JWTException $e) {
            return $next($request);
        }

        // Check that route is allowed
        $routeName = $request->route()->getName();
        if (!in_array($routeName, self::ALLOWED_ROUTES)) {
            return response([
                'message' => 'Unauthorized when impersonating',
                'route' => $routeName,
                'impersonator' => Auth::guard('api')->payload()->get('impersonator_id'),
                'impersonated_account' => Auth::id(),
                'url' => $request->fullUrl(),
                'payload' => $request->all(),
            ], 403);
        } elseif (in_array($routeName, self::LOG_ROUTES)) {
            Log::warning(self::ADMIN_LOG_PREFIX . 'Impersonator action', [
                'route' => $routeName,
                'url' => $request->fullUrl(),
                'impersonated_account' => Auth::id(),
                'impersonator' => Auth::guard('api')->payload()->get('impersonator_id'),
                'payload' => $request->all(),
            ]);
        }

        return $next($request);
    }
}
