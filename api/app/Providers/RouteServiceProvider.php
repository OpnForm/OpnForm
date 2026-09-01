<?php

namespace App\Providers;

use App\Models\Forms\AgentFormDraft;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();
        $this->registerGlobalRouteParamConstraints();

        $this->routes(function () {
            Route::middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('api-external')
                ->namespace($this->namespace)
                ->group(base_path('routes/api-external.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $ip = $request->ip();
            $requestedEmail = $request->input('email');
            $email = is_string($requestedEmail) ? strtolower(trim($requestedEmail)) : 'invalid';
            $emailKey = hash('sha256', $email !== '' ? $email : 'unknown');

            return [
                Limit::perMinute(5)->by('password-reset:minute:ip:' . $ip),
                Limit::perHour(30)->by('password-reset:hour:ip:' . $ip),
                Limit::perHour(5)->by('password-reset:hour:email:' . $emailKey),
            ];
        });

        RateLimiter::for('oidc-init', function (Request $request) {
            $connection = (string) ($request->route('slug') ?? 'unknown');
            $key = hash('sha256', $request->ip() . '|' . $connection);
            $limit = max(1, (int) config('oidc.rate_limit_per_minute', 30));

            return Limit::perMinute($limit)
                ->by('oidc-init:' . $key)
                ->response(function (Request $request, array $headers) {
                    $retryAfter = max(1, (int) ($headers['Retry-After'] ?? 60));

                    return response()->json([
                        'error' => 'oidc_rate_limited',
                        'message' => "Too many sign-in requests. Please try again in {$retryAfter} seconds.",
                        'retry_after' => $retryAfter,
                    ], 429, $headers);
                });
        });

        // Export endpoints use dedicated buckets so long-running CSV exports
        // are not blocked by the general API rate limit.
        RateLimiter::for('export', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('export-status', function (Request $request) {
            return Limit::perMinute(180)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('ai-formula-generation', function (Request $request) {
            $identifier = $request->user()
                ? 'user:' . $request->user()->getAuthIdentifier()
                : 'ip:' . $request->ip();

            return [
                Limit::perMinute(10)->by('ai-formula-generation:minute:' . $identifier),
                Limit::perHour(100)->by('ai-formula-generation:hour:' . $identifier),
            ];
        });

        RateLimiter::for('public-uploads', function (Request $request) {
            $identifier = $request->user()
                ? 'user:' . $request->user()->getAuthIdentifier()
                : 'ip:' . $request->ip();
            $route = $request->route()?->getName() ?? $request->path();
            $key = $route . ':' . $identifier;

            return [
                Limit::perMinute(max(1, config('opnform.public_uploads.rate_limit.per_minute', 30)))
                    ->by('public-uploads:minute:' . $key),
                Limit::perHour(max(1, config('opnform.public_uploads.rate_limit.per_hour', 300)))
                    ->by('public-uploads:hour:' . $key),
            ];
        });

        RateLimiter::for('mcp', function (Request $request) {
            $identifier = $request->user()
                ? 'user:'.$request->user()->getAuthIdentifier()
                : 'ip:'.$request->ip();

            return [
                Limit::perMinute(max(1, config('opnform.mcp.rate_limit.per_minute', 120)))
                    ->by('mcp:minute:'.$identifier),
                Limit::perHour(max(1, config('opnform.mcp.rate_limit.per_hour', 3000)))
                    ->by('mcp:hour:'.$identifier),
            ];
        });

        RateLimiter::for('mcp-oauth-registration', function (Request $request) {
            return Limit::perHour(20)->by('mcp-oauth-registration:'.$request->ip());
        });

        RateLimiter::for('agent-draft-preview', function (Request $request) {
            $routeDraft = $request->route('draft');
            $draftId = (string) ($routeDraft instanceof AgentFormDraft ? $routeDraft->getKey() : $routeDraft);
            $identifier = ctype_digit($draftId)
                ? 'draft:'.$draftId
                : 'invalid:'.$request->ip();

            return [
                Limit::perMinute(max(1, config('opnform.mcp.rate_limit.draft_preview_requests_per_minute', 240)))
                    ->by('agent-draft-preview:'.$identifier),
                Limit::perMinute(max(1, config('opnform.mcp.rate_limit.draft_proxy_pool_per_minute', 6000)))
                    ->by('agent-draft-preview:proxy:'.$request->ip()),
            ];
        });

        RateLimiter::for('agent-draft-handoff', function (Request $request) {
            $token = (string) $request->input('handoff_token');
            $identifier = strlen($token) === 43
                ? 'token:'.hash('sha256', $token)
                : 'invalid:'.$request->ip();

            return [
                Limit::perMinute(max(1, config('opnform.mcp.rate_limit.draft_handoffs_per_minute', 120)))
                    ->by('agent-draft-handoff:'.$identifier),
                Limit::perMinute(max(1, config('opnform.mcp.rate_limit.draft_proxy_pool_per_minute', 6000)))
                    ->by('agent-draft-handoff:proxy:'.$request->ip()),
            ];
        });

        RateLimiter::for('agent-draft-editor', function (Request $request) {
            $session = (string) $request->header('x-agent-draft-session');
            $identifier = strlen($session) === 43
                ? 'session:'.hash('sha256', $session)
                : 'missing:'.$request->ip();
            $route = $request->route()?->getName() ?? $request->path();

            return [
                Limit::perMinute(max(1, config('opnform.mcp.rate_limit.draft_editor_requests_per_minute', 240)))
                    ->by('agent-draft-editor:'.$route.':'.$identifier),
                Limit::perMinute(max(1, config('opnform.mcp.rate_limit.draft_proxy_pool_per_minute', 6000)))
                    ->by('agent-draft-editor:proxy:'.$request->ip()),
            ];
        });
    }

    protected function registerGlobalRouteParamConstraints()
    {
        Route::pattern('workspaceId', '[0-9]+');
    }
}
