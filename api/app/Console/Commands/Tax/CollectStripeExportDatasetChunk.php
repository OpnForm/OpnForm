<?php

namespace App\Console\Commands\Tax;

use App\Services\Tax\StripeBalanceSummaryService;
use App\Services\Tax\StripeExportDatasetService;
use App\Services\Tax\StripeExportDatasetStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CollectStripeExportDatasetChunk extends Command
{
    protected $signature = 'stripe:collect-export-dataset-chunk
                            {--dataset= : Dataset id}
                            {--start-date= : Start date (YYYY-MM-DD)}
                            {--end-date= : End date (YYYY-MM-DD)}';

    protected $description = 'Collect a single Stripe export dataset chunk';

    public function handle(
        StripeExportDatasetService $collector,
        StripeExportDatasetStore $store,
        StripeBalanceSummaryService $balanceSummaryService
    ): int
    {
        $datasetId = (string) $this->option('dataset');
        $startDate = (string) $this->option('start-date');
        $endDate = (string) $this->option('end-date');

        if (!$datasetId || !$startDate || !$endDate) {
            $this->error('dataset, start-date and end-date are required.');
            return Command::FAILURE;
        }

        $chunkKey = "{$startDate}_{$endDate}";
        Cache::put("stripe-export-dataset:{$datasetId}:{$chunkKey}", [
            'status' => 'running',
            'started_at' => now()->toIso8601String(),
        ], now()->addDay());

        $payload = $collector->collect($startDate, $endDate);
        $balanceSummary = $balanceSummaryService->summarize($startDate, $endDate);
        $store->writeChunk($datasetId, $chunkKey, $payload['rows'], $payload['stats'], $balanceSummary);

        Cache::put("stripe-export-dataset:{$datasetId}:{$chunkKey}", [
            'status' => 'completed',
            'completed_at' => now()->toIso8601String(),
            'row_count' => count($payload['rows']),
            'stats' => $payload['stats'],
            'balance_summary' => $balanceSummary,
        ], now()->addDay());

        $this->info("Collected {$chunkKey}: " . count($payload['rows']) . ' rows');

        return Command::SUCCESS;
    }
}
