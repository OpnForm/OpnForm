<?php

use App\Mcp\Servers\OpnFormServer;
use App\Support\Mcp\McpAvailability;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

if (app(McpAvailability::class)->enabled()) {
    Mcp::oauthRoutes();
    Route::post('/oauth/register', OAuthRegisterController::class)
        ->middleware('throttle:mcp-oauth-registration');
    Mcp::web('/mcp', OpnFormServer::class)->middleware(['auth.mcp.optional', 'throttle:mcp']);
    Mcp::local('opnform', OpnFormServer::class);
}
