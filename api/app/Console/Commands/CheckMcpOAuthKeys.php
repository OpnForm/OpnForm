<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\Passport;

class CheckMcpOAuthKeys extends Command
{
    protected $signature = 'mcp:check-oauth-keys {--environment-only : Require keys from PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY}';

    protected $description = 'Fail when the stable Passport key pair required by MCP OAuth is missing or invalid';

    public function handle(): int
    {
        $privateKey = $this->keyContents('private');
        $publicKey = $this->keyContents('public');

        if ($privateKey === null || $publicKey === null) {
            $message = $this->option('environment-only')
                ? 'MCP OAuth requires both PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY.'
                : 'MCP OAuth requires both PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY, or a complete Passport key pair in storage.';
            $this->components->error($message);

            return self::FAILURE;
        }

        $private = openssl_pkey_get_private($privateKey);
        $public = openssl_pkey_get_public($publicKey);
        if ($private === false || $public === false) {
            $this->components->error('The configured Passport key pair is not valid PEM.');

            return self::FAILURE;
        }

        $privateDetails = openssl_pkey_get_details($private);
        $publicDetails = openssl_pkey_get_details($public);
        if (! is_array($privateDetails)
            || ! is_array($publicDetails)
            || ! hash_equals($privateDetails['key'], $publicDetails['key'])) {
            $this->components->error('The configured Passport private and public keys do not belong to the same pair.');

            return self::FAILURE;
        }

        $this->components->info('Passport OAuth keys are configured and valid.');

        return self::SUCCESS;
    }

    private function keyContents(string $type): ?string
    {
        $configured = config("passport.{$type}_key");
        if (is_string($configured) && trim($configured) !== '') {
            return str_replace('\\n', "\n", $configured);
        }

        if ($this->option('environment-only')) {
            return null;
        }

        $path = Passport::keyPath("oauth-{$type}.key");

        return is_file($path) ? file_get_contents($path) ?: null : null;
    }
}
