<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

class FormExportService
{
    public const SYNC_EXPORT_THRESHOLD = 1000;
    public const CACHE_KEY_PREFIX = 'form_export_job';
    public const EXPORT_FILE_PATH = 'exports/temp/';

    /**
     * Determine if export should be processed synchronously or asynchronously
     */
    public function shouldExportAsync(Form $form): bool
    {
        return $form->submissions()->count() > self::SYNC_EXPORT_THRESHOLD;
    }

    /**
     * Format a single submission for export
     */
    public function formatSubmissionForExport(Form $form, FormSubmission $submission, array $displayColumns): array
    {
        $formatter = (new FormSubmissionFormatter($form, $submission->data))
            ->outputStringsOnly()
            ->setEmptyForNoValue()
            ->showRemovedFields()
            ->showHiddenFields()
            ->useSignedUrlForFiles();

        $formattedData = $formatter->getCleanKeyValue();
        $filteredData = ['id' => Hashids::encode($submission->id)];

        foreach ($displayColumns as $column => $value) {
            if ($value === true) {
                $key = collect($formattedData)->keys()->first(fn ($key) => str_contains($key, $column));
                if ($key) {
                    $filteredData[$key] = $formattedData[$key];
                }
            }
        }

        if (isset($displayColumns['created_at']) && $displayColumns['created_at'] === true) {
            $filteredData['created_at'] = $submission->created_at->format('Y-m-d H:i');
        }

        // Add status column if partial submissions are enabled
        if ($form->enable_partial_submissions) {
            $filteredData['status'] = $submission->status === FormSubmission::STATUS_PARTIAL ? 'In Progress' : 'Completed';
        }

        return $filteredData;
    }

    /**
     * Generate CSV content from rows and upload to storage
     */
    public function generateAndUploadCsvFile(array $rows, string $fileName): string
    {
        if (empty($rows)) {
            throw new \Exception('No data to export');
        }

        // Generate CSV content
        $csvContent = $this->generateCsvContent($rows);

        // Upload to configured storage disk
        $filePath = self::EXPORT_FILE_PATH . $fileName;
        $disk = Storage::disk(config('filesystems.default'));

        $disk->put($filePath, $csvContent, [
            'visibility' => 'private',
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"'
        ]);

        // Generate temporary URL for download (works for all storage types)
        return $disk->temporaryUrl($filePath, now()->addDay());
    }

    /**
     * Generate CSV content from array of rows
     */
    private function generateCsvContent(array $rows): string
    {
        if (empty($rows)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Write header row (clean column names)
        $headers = $this->cleanColumnNames(array_keys($rows[0]));
        fputcsv($output, $headers);

        // Write data rows
        foreach ($rows as $row) {
            fputcsv($output, array_values($row));
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    /**
     * Clean column names by removing parenthetical content
     */
    private function cleanColumnNames(array $columnNames): array
    {
        return collect($columnNames)->map(function ($columnName) {
            return preg_replace('/\s\(.*\)/', '', $columnName);
        })->toArray();
    }

    /**
     * Initialize async export job and return job ID
     */
    public function initializeAsyncExport(Form $form, int $userId): string
    {
        $jobId = (string) Str::uuid();
        $cacheKey = $this->getCacheKey($jobId);

        // Initialize cache entry before dispatching job to prevent race condition
        Cache::put($cacheKey, [
            'status' => 'queued',
            'progress' => 0,
            'form_id' => $form->id,
            'user_id' => $userId,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
            'processed_submissions' => null,
            'total_submissions' => null,
            'file_url' => null,
            'error_message' => null,
            'expires_at' => null,
        ], now()->addHours(2));

        return $jobId;
    }

    /**
     * Generate cache key for export job
     */
    public function getCacheKey(string $jobId): string
    {
        return self::CACHE_KEY_PREFIX . ':' . $jobId;
    }
}
