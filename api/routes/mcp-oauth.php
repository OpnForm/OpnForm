<?php

use App\Http\Controllers\Mcp\McpOAuthMetadataController;
use App\Http\Controllers\Mcp\OAuthLoginController;
use Illuminate\Support\Facades\Route;

// Well-known metadata (no auth required)
Route::get('.well-known/oauth-protected-resource', [McpOAuthMetadataController::class, 'protectedResource']);
Route::get('.well-known/oauth-authorization-server', [McpOAuthMetadataController::class, 'authorizationServer']);

// Session-based login for OAuth authorization flow
Route::get('oauth/login', [OAuthLoginController::class, 'showLogin']);
Route::post('oauth/login', [OAuthLoginController::class, 'handleLogin']);
