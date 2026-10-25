<?php

namespace App\Providers;

use App\Service\OAuth\McpPassportClientRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

final class OAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Passport::ignoreRoutes();

        // Laravel MCP currently resolves Passport's concrete repository from
        // the container. This adapter keeps dynamic PKCE registration working
        // with Passport 12 until the project can upgrade to Passport 13.
        $this->app->singleton(ClientRepository::class, function () {
            $personalAccessClient = config('passport.personal_access_client', []);

            return new McpPassportClientRepository(
                $personalAccessClient['id'] ?? null,
                $personalAccessClient['secret'] ?? null
            );
        });
    }

    public function boot(): void
    {
        if (! config('oauth.enabled', false)) {
            return;
        }

        Passport::authorizationView('oauth.authorize');
        Passport::tokensCan(config('oauth.scopes', []));
        Passport::tokensExpireIn(now()->addMinutes(max(1, (int) config('oauth.access_token_ttl', 10080))));
        Passport::refreshTokensExpireIn(now()->addDays(max(1, (int) config('oauth.refresh_token_ttl_days', 30))));

        Route::middleware('mcp.enabled')->group(base_path('routes/oauth.php'));
    }
}
