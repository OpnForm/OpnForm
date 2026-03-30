<?php

namespace App\Console\Commands;

use App\Models\Integration\FormIntegrationsEvent;
use Illuminate\Console\Command;

class CleanIntegrationEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'forms:integration-events-cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete Old Integration Events';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $response = FormIntegrationsEvent::where('created_at', '<', now()->subDays(14))->delete();
        $this->line($response . ' Events Deleted');

        return Command::SUCCESS;
    }
}
