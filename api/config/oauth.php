<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Delegated OAuth 2.1
    |--------------------------------------------------------------------------
    |
    | Passport is reserved for third-party clients that need delegated access.
    | It remains separate from the JWT used by the first-party frontend and
    | from Sanctum personal access tokens.
    |
    */
    // This controls whether the OAuth infrastructure is available. Runtime
    // MCP access is gated separately by McpAvailability so self-hosted admins
    // can toggle it without rebuilding Laravel's route cache.
    'enabled' => env('OAUTH_ENABLED', true),

    'access_token_ttl' => (int) env('OAUTH_ACCESS_TOKEN_TTL', 60 * 24 * 7),
    'refresh_token_ttl_days' => (int) env('OAUTH_REFRESH_TOKEN_TTL_DAYS', 30),

    'scopes' => [
        'mcp:use' => 'Use OpnForm MCP features to access the forms and submissions you explicitly ask the assistant to manage.',
    ],
];
