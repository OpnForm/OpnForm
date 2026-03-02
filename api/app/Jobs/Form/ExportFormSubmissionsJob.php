<?php

namespace App\Jobs\Form;

use App\Models\Forms\Form;
use App\Service\Forms\FormExportService;
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

    public function __construct(
        public Form $form,
        public array $columns,
        public array $submissionIds,
        public string $jobId,
        public int $userId
    ) {
    }

    public function handle(FormExportService $exportService): void
    {
        // Initialize job status in cache
        $this->updateJobStatus('processing', 0);

        $submissionQuery = $this->form->submissions()->orderBy('created_at', 'desc');
        if (!empty($this->submissionIds)) {
            $submissionQuery->whereIn('id', $this->submissionIds);
        }

        // Get total submission count
        $totalSubmissions = (clone $submissionQuery)->count();

        if ($totalSubmissions === 0) {
            $this->updateJobStatus('failed', 0, 'No submissions to export');
            return;
        }

        // Process submissions in chunks
        $chunkSize = 500;
        $processedCount = 0;
        $allRows = [];

        $submissionQuery->chunk($chunkSize, function ($submissions) use (&$processedCount, &$allRows, $totalSubmissions, $exportService) {
            foreach ($submissions as $submission) {
                $formattedRow = $exportService->formatSubmissionForExport(
                    $this->form,
                    $submission,
                    $this->columns
                );
                $allRows[] = $formattedRow;
                $processedCount++;
            }

            // Update progress
            $progress = min(90, ($processedCount / $totalSubmissions) * 90); // Reserve 10% for file generation
            $this->updateJobStatus('processing', $progress, null, $processedCount, $totalSubmissions);
        });

        // Generate and upload file to S3
        $this->updateJobStatus('processing', 95, 'Generating export file...');

        $fileName = $this->form->slug . '-submissions-' . now()->format('Y-m-d-H-i-s') . '.csv';
        $fileUrl = $exportService->generateAndUploadCsvFile($allRows, $fileName);

        // Mark as completed
        $this->updateJobStatus('completed', 100, null, $totalSubmissions, $totalSubmissions, $fileUrl);

        Log::info("Export job {$this->jobId} completed successfully", [
            'form_id' => $this->form->id,
            'total_submissions' => $totalSubmissions,
            'file_url' => $fileUrl
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
        ?string $fileUrl = null
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
            $data['expires_at'] = now()->addDay()->toISOString(); // File expires in 24 hours
        }

        // Cache for 2 hours
        Cache::put($cacheKey, $data, now()->addHours(2));
    }
}
