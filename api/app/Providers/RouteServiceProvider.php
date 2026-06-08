<?php

namespace App\Providers;

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

        // Rate limit for summary endpoints: 30 requests per minute per user
        RateLimiter::for('summary', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Export endpoints use dedicated buckets so long-running CSV exports
        // are not blocked by the general API rate limit.
        RateLimiter::for('export', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('export-status', function (Request $request) {
            return Limit::perMinute(180)->by($request->user()?->id ?: $request->ip());
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
    }

    protected function registerGlobalRouteParamConstraints()
    {
        Route::pattern('workspaceId', '[0-9]+');
    }
}
