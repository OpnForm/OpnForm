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
}
