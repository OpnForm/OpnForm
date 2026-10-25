<?php

namespace App\Http\Controllers;

use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;
use Laravel\Passport\Client;

class McpOAuthRegisterController extends OAuthRegisterController
{
    protected function grantMcpScope(mixed $client): void
    {
        // MCP 1.x refreshes the model here. Preserve the Passport 13 response
        // attributes supplied by our Passport 12 repository bridge.
        $redirectUris = $client instanceof Client ? $client->getAttribute('redirect_uris') : null;
        $grantTypes = $client instanceof Client ? $client->getAttribute('grant_types') : null;

        parent::grantMcpScope($client);

        if ($client instanceof Client) {
            $client->setAttribute('redirect_uris', $redirectUris);
            $client->setAttribute('grant_types', $grantTypes);
        }
    }

    protected function isValidRedirectUri(string $value): bool
    {
        // Passport 12 persists multiple redirects as a comma-delimited string.
        // A literal comma inside one URI would become an unvalidated redirect
        // when Passport reads the client back from storage.
        return strlen($value) <= 2048
            && ! str_contains($value, ',')
            && parent::isValidRedirectUri($value);
    }

    protected function isLocalhostUrl(string $url): bool
    {
        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'http') {
            return false;
        }

        if (parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && in_array(
            strtolower(trim($host, '[]')),
            ['localhost', '127.0.0.1', '::1'],
            true,
        );
    }
}
