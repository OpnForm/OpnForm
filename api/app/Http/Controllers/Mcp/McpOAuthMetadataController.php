<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use Laravel\Passport\Passport;

class McpOAuthMetadataController extends Controller
{
    /**
     * GET /.well-known/oauth-protected-resource
     */
    public function protectedResource()
    {
        $issuer = rtrim(config('app.url'), '/');

        return response()->json([
            'resource' => $issuer,
            'authorization_servers' => [$issuer],
            'scopes_supported' => Passport::scopeIds(),
        ]);
    }

    /**
     * GET /.well-known/oauth-authorization-server
     */
    public function authorizationServer()
    {
        $issuer = rtrim(config('app.url'), '/');

        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/oauth/authorize',
            'token_endpoint' => $issuer . '/oauth/token',
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'none'],
            'code_challenge_methods_supported' => ['S256'],
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'scopes_supported' => Passport::scopeIds(),
        ]);
    }
}
