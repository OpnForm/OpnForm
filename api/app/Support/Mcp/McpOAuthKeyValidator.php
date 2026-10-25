<?php

namespace App\Support\Mcp;

use Laravel\Passport\Passport;

final class McpOAuthKeyValidator
{
    /**
     * @return array<int, array{code: string, message: string}>
     */
    public function blockers(bool $environmentOnly = false): array
    {
        $privateKey = $this->configuredKey('private');
        $publicKey = $this->configuredKey('public');

        if ($privateKey === null && $publicKey === null && ! $environmentOnly) {
            $privateKey = $this->storedKey('private');
            $publicKey = $this->storedKey('public');
        }

        if ($privateKey === null || $publicKey === null) {
            return [[
                'code' => 'passport_keys_missing',
                'message' => $environmentOnly
                    ? 'MCP OAuth requires both PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY.'
                    : 'MCP OAuth requires both PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY, or a complete Passport key pair in storage.',
            ]];
        }

        $private = openssl_pkey_get_private($privateKey);
        $public = openssl_pkey_get_public($publicKey);
        if ($private === false || $public === false) {
            return [[
                'code' => 'passport_keys_invalid',
                'message' => 'The configured Passport key pair is not valid PEM.',
            ]];
        }

        $privateDetails = openssl_pkey_get_details($private);
        $publicDetails = openssl_pkey_get_details($public);
        if (! is_array($privateDetails)
            || ! is_array($publicDetails)
            || ! hash_equals($privateDetails['key'], $publicDetails['key'])) {
            return [[
                'code' => 'passport_keys_mismatched',
                'message' => 'The configured Passport private and public keys do not belong to the same pair.',
            ]];
        }

        return [];
    }

    private function configuredKey(string $type): ?string
    {
        $configured = config("passport.{$type}_key");
        if (is_string($configured) && trim($configured) !== '') {
            return str_replace('\\n', "\n", $configured);
        }

        return null;
    }

    private function storedKey(string $type): ?string
    {
        $path = Passport::keyPath("oauth-{$type}.key");

        return is_file($path) ? file_get_contents($path) ?: null : null;
    }
}
