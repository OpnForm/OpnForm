<?php

namespace App\Console\Commands;

use App\Models\Forms\AgentFormDraft;
use Illuminate\Console\Command;

class PurgeExpiredAgentFormDrafts extends Command
{
    protected $signature = 'agent-drafts:purge-expired';

    protected $description = 'Purge expired guest form drafts created through MCP and agent integrations';

    public function handle(): int
    {
        $deleted = 0;

        AgentFormDraft::query()
            ->where('expires_at', '<=', now())
            ->select('id')
            ->chunkById(500, function ($drafts) use (&$deleted) {
                $ids = $drafts->pluck('id');
                $deleted += AgentFormDraft::query()->whereKey($ids)->delete();
            });

        $this->info("Purged {$deleted} expired agent form draft(s).");

        return Command::SUCCESS;
    }
}
