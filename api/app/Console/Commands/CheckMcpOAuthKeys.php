<?php

namespace App\Console\Commands;

use App\Support\Mcp\McpOAuthKeyValidator;
use Illuminate\Console\Command;

class CheckMcpOAuthKeys extends Command
{
    protected $signature = 'mcp:check-oauth-keys {--environment-only : Require keys from PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY}';

    protected $description = 'Fail when the stable Passport key pair required by MCP OAuth is missing or invalid';

    public function handle(McpOAuthKeyValidator $keys): int
    {
        $blockers = $keys->blockers((bool) $this->option('environment-only'));
        if ($blockers !== []) {
            $this->components->error($blockers[0]['message']);

            return self::FAILURE;
        }

        $this->components->info('Passport OAuth keys are configured and valid.');

        return self::SUCCESS;
    }
}
