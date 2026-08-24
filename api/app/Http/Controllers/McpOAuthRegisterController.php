<?php

namespace App\Http\Controllers;

use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

class McpOAuthRegisterController extends OAuthRegisterController
{
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
