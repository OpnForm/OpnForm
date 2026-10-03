<?php

use App\Mcp\Servers\OpnFormServer;
use App\Http\Controllers\McpOAuthRegisterController;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

if (config('oauth.enabled', false)) {
    Route::middleware('mcp.enabled')->group(function () {
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
        Route::post('/oauth/register', McpOAuthRegisterController::class)
            ->middleware('throttle:mcp-oauth-registration');
    });
}

Route::middleware('mcp.enabled')->group(function () {
    Mcp::web('/mcp', OpnFormServer::class)
        ->middleware(['auth.mcp.optional', 'throttle:mcp', 'observe.mcp']);
});
Mcp::local('opnform', OpnFormServer::class);
