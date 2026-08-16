<?php

use App\Mcp\Servers\OpnFormServer;
use App\Support\Mcp\McpAvailability;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

if (app(McpAvailability::class)->enabled()) {
    if (config('oauth.enabled', false)) {
        Route::get('/.well-known/oauth-authorization-server', static fn () => response()->json([
            'issuer' => config('mcp.authorization_server') ?? url('/'),
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'registration_endpoint' => url('/oauth/register'),
            'response_types_supported' => ['code'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => ['mcp:use'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none'],
        ]))->name('mcp.oauth.authorization-server');

        Mcp::oauthRoutes();
        Route::post('/oauth/register', OAuthRegisterController::class)
            ->middleware('throttle:mcp-oauth-registration');
    }

    Mcp::web('/mcp', OpnFormServer::class)->middleware(['auth.mcp.optional', 'throttle:mcp', 'observe.mcp']);
    Mcp::local('opnform', OpnFormServer::class);
}
