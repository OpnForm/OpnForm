<?php

namespace App\Console\Commands\Tax;

use App\Services\Tax\StripeExportDatasetStore;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BuildStripeExportDataset extends Command
{
    protected $signature = 'stripe:build-export-dataset
                            {--dataset= : Dataset id}
                            {--start-date= : Start date (YYYY-MM-DD)}
                            {--end-date= : End date (YYYY-MM-DD)}
                            {--full-month : Use the full month of the start date}
                            {--chunk=week : Chunk by week or month}
                            {--concurrency=4 : Max concurrent chunk collectors}';

    protected $description = 'Build a reusable Stripe export dataset in chunked parallel batches';

    public function handle(StripeExportDatasetStore $store): int
    {
        [$startDate, $endDate] = $this->resolveDateRange();
        if (!$startDate || !$endDate) {
            return Command::FAILURE;
        }

        $chunkBy = (string) $this->option('chunk');
        $concurrency = max(1, (int) $this->option('concurrency'));
        $datasetId = (string) ($this->option('dataset') ?: "{$startDate}_{$endDate}_{$chunkBy}");

        $chunks = $this->buildChunks($startDate, $endDate, $chunkBy);
        $store->initialize($datasetId, [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'chunk_by' => $chunkBy,
            'concurrency' => $concurrency,
        ]);

        $this->info("Dataset: {$datasetId}");
        $this->info('Chunks: ' . count($chunks));

        $pending = $chunks;
        $running = [];

        while (!empty($pending) || !empty($running)) {
            while (count($running) < $concurrency && !empty($pending)) {
                $chunk = array_shift($pending);
                $process = new Process([
                    PHP_BINARY,
                    base_path('artisan'),
                    'stripe:collect-export-dataset-chunk',
                    '--dataset=' . $datasetId,
                    '--start-date=' . $chunk['start_date'],
                    '--end-date=' . $chunk['end_date'],
                ], base_path());
                $process->setTimeout(null);
                $process->start();

                $chunk['process'] = $process;
                $running[] = $chunk;
                $this->line("Started chunk {$chunk['start_date']} -> {$chunk['end_date']}");
            }

            foreach ($running as $index => $chunk) {
                /** @var Process $process */
                $process = $chunk['process'];
                if (!$process->isRunning()) {
                    if (!$process->isSuccessful()) {
                        $this->error("Chunk failed {$chunk['start_date']} -> {$chunk['end_date']}");
                        $this->line($process->getErrorOutput() ?: $process->getOutput());
                        return Command::FAILURE;
                    }

                    $this->line("Completed chunk {$chunk['start_date']} -> {$chunk['end_date']}");
                    unset($running[$index]);
                }
            }

            usleep(200000);
        }

        $rows = $store->mergeChunks($datasetId);
        $this->info('Dataset ready: ' . $store->datasetRowsPath($datasetId));
        $this->info('Rows: ' . count($rows));

        return Command::SUCCESS;
    }

    private function resolveDateRange(): array
    {
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');

        if (!$startDate) {
            $startDate = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
        } elseif (!Carbon::createFromFormat('Y-m-d', $startDate)) {
            $this->error('Invalid start date format. Use YYYY-MM-DD.');
            return [null, null];
        }

        if (!$endDate) {
            $endDate = Carbon::parse($startDate)->endOfMonth()->format('Y-m-d');
        } elseif (!Carbon::createFromFormat('Y-m-d', $endDate)) {
            $this->error('Invalid end date format. Use YYYY-MM-DD.');
            return [null, null];
        }

        return [$startDate, $endDate];
    }

    private function buildChunks(string $startDate, string $endDate, string $chunkBy): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        $chunks = [];

        if ($chunkBy === 'month') {
            $cursor = $start->copy()->startOfMonth();
            while ($cursor->lte($end)) {
                $chunkStart = $cursor->copy()->max($start);
                $chunkEnd = $cursor->copy()->endOfMonth()->min($end);
                $chunks[] = [
                    'start_date' => $chunkStart->format('Y-m-d'),
                    'end_date' => $chunkEnd->format('Y-m-d'),
                ];
                $cursor->addMonth()->startOfMonth();
            }

            return $chunks;
        }

        foreach (CarbonPeriod::create($start, '7 days', $end) as $periodStart) {
            $chunkStart = $periodStart->copy()->max($start);
            $chunkEnd = $periodStart->copy()->addDays(6)->endOfDay()->min($end);
            $chunks[] = [
                'start_date' => $chunkStart->format('Y-m-d'),
                'end_date' => $chunkEnd->format('Y-m-d'),
            ];
        }

        return $chunks;
    }
}
