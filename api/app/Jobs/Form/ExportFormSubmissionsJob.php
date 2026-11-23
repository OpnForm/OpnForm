<?php

namespace App\Jobs\Form;

use App\Models\Forms\Form;
use App\Service\Forms\FormExportService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExportFormSubmissionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 900; // 15 minutes
    public $tries = 1;

    public string $format = 'csv';

    public function __construct(
        public Form $form,
        public array $columns,
        public array $submissionIds,
        public string $jobId,
        public int $userId,
        string $format = 'csv'
    ) {
        $this->format = $format;
    }

    public function handle(FormExportService $exportService): void
    {
        // Initialize job status in cache
        $this->updateJobStatus('processing', 0);

        $submissionQuery = $exportService->submissionQuery($this->form, $this->submissionIds);

        // Get total submission count
        $totalSubmissions = (clone $submissionQuery)->count();

        if ($totalSubmissions === 0) {
            $this->updateJobStatus('failed', 0, 'No submissions to export');
            return;
        }

        $chunkSize = 500;
        $processedCount = 0;
        $fileLinkExpiresAt = $exportService->fileLinkExpiresAt($this->form);
        $numericColumns = $exportService->numericColumns($this->form, $this->format);
        $rows = function () use ($submissionQuery, $chunkSize, $totalSubmissions, $exportService, $fileLinkExpiresAt, $numericColumns, &$processedCount) {
            foreach ($exportService->iterateSubmissions($submissionQuery, $chunkSize) as $submission) {
                yield $exportService->formatSubmissionForExport(
                    $this->form,
                    $submission,
                    $this->columns,
                    $fileLinkExpiresAt,
                    $numericColumns
                );

                $processedCount++;
                if ($processedCount % $chunkSize === 0) {
                    $progress = min(90, ($processedCount / $totalSubmissions) * 90);
                    $this->updateJobStatus('processing', $progress, null, $processedCount, $totalSubmissions);
                }
            }

            if ($processedCount === 0) {
                throw new \RuntimeException('No submissions to export');
            }

            $this->updateJobStatus('processing', 95, 'Finalizing export file...', $processedCount, $processedCount);
        };

        $fileName = $this->form->slug . '-submissions-' . now()->format('Y-m-d-H-i-s') . '.' . $this->format;
        $fileUrl = $exportService->generateAndUploadFile(
            $rows(),
            $this->jobId . '/' . $fileName,
            $fileLinkExpiresAt,
            $this->format
        );

        // Mark as completed
        $this->updateJobStatus('completed', 100, null, $processedCount, $processedCount, $fileUrl, $fileLinkExpiresAt);

        Log::info("Export job {$this->jobId} completed successfully", [
            'form_id' => $this->form->id,
            'total_submissions' => $processedCount,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->updateJobStatus('failed', 0, $exception->getMessage());

        Log::error("Export job {$this->jobId} failed in failed() method", [
            'form_id' => $this->form->id,
            'error' => $exception->getMessage()
        ]);
    }

    private function updateJobStatus(
        string $status,
        int $progress,
        ?string $errorMessage = null,
        ?int $processedSubmissions = null,
        ?int $totalSubmissions = null,
        ?string $fileUrl = null,
        ?Carbon $fileExpiresAt = null
    ): void {
        $exportService = app(FormExportService::class);
        $cacheKey = $exportService->getCacheKey($this->jobId);

        // Get existing data to preserve created_at
        $existingData = Cache::get($cacheKey, []);

        $data = [
            'status' => $status,
            'progress' => $progress,
            'form_id' => $this->form->id,
            'user_id' => $this->userId,
            'created_at' => $existingData['created_at'] ?? now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ];

        if ($errorMessage) {
            $data['error_message'] = $errorMessage;
        }

        if ($processedSubmissions !== null) {
            $data['processed_submissions'] = $processedSubmissions;
        }

        if ($totalSubmissions !== null) {
            $data['total_submissions'] = $totalSubmissions;
        }

        if ($fileUrl) {
            $data['file_url'] = $fileUrl;
            $data['expires_at'] = ($fileExpiresAt ?? now()->addDay())->toISOString();
        }

        // Cache for 2 hours
        Cache::put($cacheKey, $data, now()->addHours(2));
    }
}
