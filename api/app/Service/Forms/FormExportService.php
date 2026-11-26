<?php

namespace App\Service\Forms;

use App\Exports\FormSubmissionExport;
use App\Exports\FormSubmissionXlsxExport;
use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use Carbon\Carbon;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Files\LocalTemporaryFile;
use Vinkla\Hashids\Facades\Hashids;

class FormExportService
{
    public const SYNC_EXPORT_THRESHOLD = 1000;

    public const CACHE_KEY_PREFIX = 'form_export_job';

    public const EXPORT_FILE_PATH = 'exports/temp/';

    public function __construct(private ExternalSubmissionFileLinkPolicy $externalFileLinkPolicy)
    {
    }

    public function fileLinkExpiresAt(Form $form): Carbon
    {
        return $this->externalFileLinkPolicy->expiresAt($form->workspace);
    }

    /**
     * Determine if export should be processed synchronously or asynchronously
     */
    public function shouldExportAsync(Form $form, ?int $submissionCount = null): bool
    {
        $count = $submissionCount ?? $form->submissions()->count();

        return $count > self::SYNC_EXPORT_THRESHOLD;
    }

    /** @return Builder<FormSubmission> */
    public function submissionQuery(Form $form, array $submissionIds): Builder
    {
        $query = $form->submissions()->getQuery();
        if ($submissionIds !== []) {
            $query->whereIn('id', $submissionIds);
        }

        $lastId = (clone $query)->max('id');

        return $query->where('id', '<=', $lastId ?? 0);
    }

    /** @param Builder<FormSubmission> $query */
    public function iterateSubmissions(Builder $query, int $chunkSize): Generator
    {
        $last = null;
        $query = (clone $query)
            ->orderByRaw('created_at IS NULL')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        do {
            $page = (clone $query)->limit($chunkSize);
            if ($last !== null) {
                $createdAt = $last->getRawOriginal('created_at');
                if ($createdAt === null) {
                    $page->whereNull('created_at')->where('id', '<', $last->id);
                } else {
                    $page->where(fn ($before) => $before
                        ->where('created_at', '<', $createdAt)
                        ->orWhere(fn ($sameTime) => $sameTime
                            ->where('created_at', $createdAt)
                            ->where('id', '<', $last->id))
                        ->orWhereNull('created_at'));
                }
            }

            $submissions = $page->get();
            yield from $submissions;
            $last = $submissions->last();
        } while ($submissions->count() === $chunkSize);
    }

    public function numericColumns(Form $form, string $format): array
    {
        if ($format !== 'xlsx') {
            return [];
        }

        return collect(array_merge($form->properties, $form->removed_properties ?? []))
            ->whereIn('type', ['number', 'rating', 'scale', 'slider'])
            ->pluck('id')
            ->flip()
            ->all();
    }

    /**
     * Format a single submission for export
     */
    public function formatSubmissionForExport(
        Form $form,
        FormSubmission $submission,
        array $displayColumns,
        ?Carbon $fileLinkExpiresAt = null,
        array $numericColumns = []
    ): array {
        $formatter = (new FormSubmissionFormatter($form, $submission->data))
            ->outputStringsOnly()
            ->setEmptyForNoValue()
            ->showRemovedFields()
            ->showHiddenFields()
            ->useSignedUrlForFiles($fileLinkExpiresAt ?? $this->fileLinkExpiresAt($form));

        $formattedData = $formatter->getCleanKeyValue();
        $filteredData = ['id' => Hashids::encode($submission->id)];

        foreach ($displayColumns as $column => $value) {
            if ($value === true) {
                if ($parameter = SubmissionAttribution::parameterFromColumnId($column)) {
                    $filteredData[$parameter] = $submission->meta['attribution'][$parameter] ?? '';

                    continue;
                }

                if ($column === 'ip_address') {
                    if ($form->enable_ip_tracking && $form->workspace->hasFeature('enable_ip_tracking')) {
                        $filteredData['ip_address'] = $submission->meta['ip_address'] ?? '';
                    }
                    // A form field can also use this ID; preserve its answer below.
                }

                $key = collect($formattedData)->keys()->first(fn ($key) => str_ends_with($key, ' ('.$column.')'));
                if ($key) {
                    $value = $formattedData[$key];
                    $filteredData[$key] = isset($numericColumns[$column]) && is_numeric($value)
                        ? $this->excelNumber($value)
                        : $value;
                }
            }
        }

        if (isset($displayColumns['created_at']) && $displayColumns['created_at'] === true) {
            $filteredData['created_at'] = $submission->created_at?->format('Y-m-d H:i') ?? '';
        }

        // Add status column if partial submissions are enabled
        if ($form->enable_partial_submissions) {
            $filteredData['status'] = $submission->status === FormSubmission::STATUS_PARTIAL ? 'In Progress' : 'Completed';
        }

        return $filteredData;
    }

    private function excelNumber(int|float|string $value): int|float|string
    {
        $text = is_float($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value;
        $number = $value + 0;
        $mantissa = preg_split('/e/i', trim($text), 2)[0];
        $digits = trim(str_replace(['.', '-', '+'], '', $mantissa), '0');

        // Keep the original digits when Excel would round or underflow the value.
        if (strlen($digits) > 15 || abs($number) > 9.99999999999999e307 ||
            ($digits !== '' && abs($number) < PHP_FLOAT_MIN) ||
            (is_float($number) && (float) (string) $number !== $number)) {
            return $text;
        }

        return $number;
    }

    public function writeFile(iterable $rows, string $format): LocalTemporaryFile
    {
        return $format === 'xlsx'
            ? (new FormSubmissionXlsxExport())->write($rows)
            : (new FormSubmissionExport())->write($rows);
    }

    public function contentType(string $format): string
    {
        return $format === 'xlsx' ? FormSubmissionXlsxExport::CONTENT_TYPE : FormSubmissionExport::CONTENT_TYPE;
    }

    /**
     * Generate a spreadsheet from rows and upload it to storage.
     */
    public function generateAndUploadFile(iterable $rows, string $relativePath, Carbon $expiresAt, string $format = 'csv'): string
    {
        $filePath = self::EXPORT_FILE_PATH.$relativePath;
        $disk = Storage::disk(config('filesystems.default'));
        $file = $this->writeFile($rows, $format);
        $stream = null;

        try {
            $stream = $file->readStream();
            $stored = $disk->put($filePath, $stream, [
                'visibility' => 'private',
                'Content-Type' => $this->contentType($format),
                'Content-Disposition' => 'attachment; filename="'.basename($relativePath).'"',
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            $file->delete();
        }

        if (!$stored) {
            throw new \RuntimeException('Unable to store submission export');
        }

        return $disk->temporaryUrl($filePath, $expiresAt);
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
        return self::CACHE_KEY_PREFIX.':'.$jobId;
    }
}
