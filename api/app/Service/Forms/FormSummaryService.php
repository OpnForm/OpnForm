<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class FormSummaryService
{
    private const TEXT_LIST_LIMIT = 10;
    private const CACHE_TTL_MINUTES = 15;

    private array $blockTypes;

    public function __construct()
    {
        $this->blockTypes = $this->loadBlockTypes();
    }

    private function loadBlockTypes(): array
    {
        $path = resource_path('data/forms/blocks_types.json');

        if (!File::exists($path)) {
            Log::warning('blocks_types.json not found, using empty config');

            return [];
        }

        $content = File::get($path);
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse blocks_types.json: ' . json_last_error_msg());

            return [];
        }

        return $decoded;
    }

    public function generateSummary(
        Form $form,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $status = 'completed'
    ): array {
        $cacheKey = $this->getCacheKey($form, $dateFrom, $dateTo, $status);

        return Cache::remember($cacheKey, self::CACHE_TTL_MINUTES * 60, function () use ($form, $dateFrom, $dateTo, $status) {
            return $this->computeSummary($form, $dateFrom, $dateTo, $status);
        });
    }

    private function getFormSummaryCacheVersionKey(Form $form): string
    {
        return 'form_summary_version_' . $form->id;
    }

    private function getFormSummaryCacheVersion(Form $form): int
    {
        return (int) Cache::get($this->getFormSummaryCacheVersionKey($form), 0);
    }

    /**
     * Clear all summary cache entries for a form by incrementing its cache version
     */
    public function clearFormSummaryCache(Form $form): void
    {
        Cache::increment($this->getFormSummaryCacheVersionKey($form));
    }

    private function computeSummary(Form $form, ?string $dateFrom, ?string $dateTo, string $status): array
    {
        $query = $this->buildBaseQuery($form, $dateFrom, $dateTo, $status);
        $totalSubmissions = $query->count();

        if ($totalSubmissions === 0) {
            return [
                'total_submissions' => 0,
                'fields' => [],
            ];
        }

        // Single query to get all submission data
        $submissions = $query
            ->select(['id', 'data', 'created_at'])
            ->orderByDesc('created_at')
            ->get();

        $inputProperties = $this->getInputProperties($form);

        // Process all fields in single pass
        $fieldSummaries = $this->processAllFields($inputProperties, $submissions, $totalSubmissions, $form->id);

        return [
            'total_submissions' => $totalSubmissions,
            'fields' => $fieldSummaries,
        ];
    }

    /**
     * Process all fields in single iteration through submissions
     * O(submissions × fields) but only ONE database query
     */
    private function processAllFields(Collection $properties, Collection $submissions, int $total, int $formId): array
    {
        // Initialize accumulators for each field
        $accumulators = [];
        foreach ($properties as $prop) {
            $accumulators[$prop['id']] = $this->initializeAccumulator($prop);
        }

        // Single pass through all submissions
        foreach ($submissions as $submission) {
            $data = $submission->data ?? [];

            // Ensure data is array (handle potential JSON parsing issues)
            if (!is_array($data)) {
                continue;
            }

            foreach ($properties as $prop) {
                $fieldId = $prop['id'];
                $value = $data[$fieldId] ?? null;

                if ($this->hasValue($value)) {
                    $this->accumulateValue($accumulators[$fieldId], $value, $prop, $submission->id);
                }
            }
        }

        // Finalize and format results
        $results = [];
        foreach ($properties as $prop) {
            $results[] = $this->finalizeField($prop, $accumulators[$prop['id']], $total, $formId);
        }

        return $results;
    }

    private function initializeAccumulator(array $property): array
    {
        $summaryType = $this->getSummaryType($property['type'] ?? '');

        return match ($summaryType) {
            'distribution' => ['counts' => [], 'answered' => 0],
            'numeric_stats' => ['values' => [], 'answered' => 0],
            'rating' => ['values' => [], 'distribution' => [], 'answered' => 0],
            'boolean' => ['true' => 0, 'false' => 0, 'answered' => 0],
            'text_list' => ['values' => [], 'answered' => 0],
            'date_summary' => ['dates' => [], 'answered' => 0],
            'matrix' => ['rows' => [], 'answered' => 0],
            'payment' => ['total_amount' => 0, 'answered' => 0],
            default => ['answered' => 0],
        };
    }

    private function accumulateValue(array &$acc, mixed $value, array $property, int $submissionId): void
    {
        $summaryType = $this->getSummaryType($property['type'] ?? '');
        $acc['answered']++;

        try {
            match ($summaryType) {
                'distribution' => $this->accumulateDistribution($acc, $value),
                'numeric_stats' => $this->accumulateNumeric($acc, $value),
                'rating' => $this->accumulateRating($acc, $value),
                'boolean' => $this->accumulateBoolean($acc, $value),
                'text_list' => $this->accumulateText($acc, $value, $submissionId),
                'date_summary' => $this->accumulateDate($acc, $value),
                'matrix' => $this->accumulateMatrix($acc, $value, $property),
                'payment' => $this->accumulatePayment($acc, $value),
                default => null,
            };
        } catch (\Throwable $e) {
            // Log but don't fail - user may have changed field type
            Log::debug('Summary accumulation error for field ' . ($property['id'] ?? 'unknown') . ': ' . $e->getMessage());
        }
    }

    private function accumulateDistribution(array &$acc, mixed $value): void
    {
        // Handle multi_select (array) and single select (string)
        $values = is_array($value) ? $value : [$value];

        foreach ($values as $v) {
            // Convert to string for counting, handle objects/arrays gracefully
            if (is_array($v) || is_object($v)) {
                $v = json_encode($v);
            }

            $v = (string) $v;

            if ($v !== '' && $v !== 'null') {
                $acc['counts'][$v] = ($acc['counts'][$v] ?? 0) + 1;
            }
        }
    }

    private function accumulateNumeric(array &$acc, mixed $value): void
    {
        // Handle string numbers, arrays with first value, etc.
        $numericValue = $this->extractNumericValue($value);

        if ($numericValue !== null) {
            $acc['values'][] = $numericValue;
        }
    }

    private function accumulateRating(array &$acc, mixed $value): void
    {
        $numericValue = $this->extractNumericValue($value);

        if ($numericValue !== null) {
            $rating = (int) round($numericValue);
            $acc['values'][] = $numericValue;
            $acc['distribution'][$rating] = ($acc['distribution'][$rating] ?? 0) + 1;
        }
    }

    private function accumulateBoolean(array &$acc, mixed $value): void
    {
        // Handle various truthy/falsy representations
        $boolValue = $this->extractBooleanValue($value);
        $key = $boolValue ? 'true' : 'false';
        $acc[$key]++;
    }

    private function accumulateText(array &$acc, mixed $value, int $submissionId): void
    {
        // Handle arrays (e.g., file uploads with multiple files)
        if (is_array($value) && !$this->isAssociativeArray($value)) {
            foreach ($value as $item) {
                if (count($acc['values']) >= self::TEXT_LIST_LIMIT) {
                    break;
                }
                $stringValue = $this->extractStringValue($item);
                if ($stringValue !== null) {
                    $acc['values'][] = [
                        'value' => $stringValue,
                        'submission_id' => $submissionId,
                    ];
                }
            }

            return;
        }

        // Convert to string, only keep first N values for preview
        $stringValue = $this->extractStringValue($value);

        if ($stringValue !== null && count($acc['values']) < self::TEXT_LIST_LIMIT) {
            $acc['values'][] = [
                'value' => $stringValue,
                'submission_id' => $submissionId,
            ];
        }
    }

    private function isAssociativeArray(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function accumulateDate(array &$acc, mixed $value): void
    {
        $dateValue = $this->extractStringValue($value);

        if ($dateValue !== null) {
            $acc['dates'][] = $dateValue;
        }
    }

    private function accumulateMatrix(array &$acc, mixed $value, array $property): void
    {
        // Matrix values are typically objects/arrays with row -> column mapping
        if (!is_array($value)) {
            return;
        }

        foreach ($value as $row => $column) {
            if (!isset($acc['rows'][$row])) {
                $acc['rows'][$row] = [];
            }

            $columnKey = is_array($column) ? json_encode($column) : (string) $column;
            $acc['rows'][$row][$columnKey] = ($acc['rows'][$row][$columnKey] ?? 0) + 1;
        }
    }

    private function accumulatePayment(array &$acc, mixed $value): void
    {
        if (is_array($value) && isset($value['amount'])) {
            $acc['total_amount'] += (float) ($value['amount'] ?? 0);
        } elseif (is_numeric($value)) {
            $acc['total_amount'] += (float) $value;
        }
    }

    /**
     * Extract numeric value from various input types
     */
    private function extractNumericValue(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $cleaned = preg_replace('/[^0-9.\-]/', '', $value);
            if (is_numeric($cleaned)) {
                return (float) $cleaned;
            }
        }

        if (is_array($value) && !empty($value)) {
            // Try first element
            return $this->extractNumericValue(reset($value));
        }

        return null;
    }

    /**
     * Extract boolean value from various input types
     */
    private function extractBooleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower($value);

            return in_array($lower, ['true', '1', 'yes', 'on'], true);
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return !empty($value);
    }

    /**
     * Extract string value from various input types
     */
    private function extractStringValue(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            // For arrays, return JSON or first element
            if (count($value) === 1) {
                return $this->extractStringValue(reset($value));
            }

            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return null;
    }

    private function finalizeField(array $property, array $acc, int $total, int $formId): array
    {
        $summaryType = $this->getSummaryType($property['type'] ?? '');

        return [
            'id' => $property['id'],
            'name' => $property['name'] ?? 'Unnamed Field',
            'type' => $property['type'] ?? 'unknown',
            'answered_count' => $acc['answered'],
            'total_submissions' => $total,
            'summary_type' => $summaryType,
            'data' => $this->formatData($summaryType, $acc, $property, $formId),
        ];
    }

    private function formatData(string $summaryType, array $acc, array $property, int $formId): array
    {
        $fieldType = $property['type'] ?? '';

        return match ($summaryType) {
            'distribution' => $this->formatDistribution($acc),
            'numeric_stats' => $this->formatNumericStats($acc),
            'rating' => $this->formatRating($acc, $property),
            'boolean' => $this->formatBoolean($acc),
            'text_list' => $this->formatTextList($acc, $fieldType, $formId),
            'date_summary' => $this->formatDateSummary($acc),
            'matrix' => $this->formatMatrix($acc, $property),
            'payment' => $this->formatPayment($acc),
            default => [],
        };
    }

    private function formatDistribution(array $acc): array
    {
        $total = array_sum($acc['counts']);
        $distribution = [];

        // Sort by count descending
        arsort($acc['counts']);

        foreach ($acc['counts'] as $value => $count) {
            $distribution[] = [
                'value' => (string) $value,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }

        return ['distribution' => $distribution];
    }

    private function formatNumericStats(array $acc): array
    {
        if (empty($acc['values'])) {
            return ['average' => null, 'min' => null, 'max' => null, 'count' => 0];
        }

        return [
            'average' => round(array_sum($acc['values']) / count($acc['values']), 2),
            'min' => min($acc['values']),
            'max' => max($acc['values']),
            'count' => count($acc['values']),
        ];
    }

    private function formatRating(array $acc, array $property): array
    {
        $maxRating = $property['rating_max_value'] ?? 5;
        $stats = $this->formatNumericStats($acc);

        // Build distribution for all possible ratings
        $distribution = [];
        for ($i = 1; $i <= $maxRating; $i++) {
            $distribution[$i] = $acc['distribution'][$i] ?? 0;
        }

        return array_merge($stats, [
            'distribution' => $distribution,
            'max_rating' => $maxRating,
        ]);
    }

    private function formatBoolean(array $acc): array
    {
        $total = $acc['true'] + $acc['false'];

        return [
            'distribution' => [
                [
                    'value' => 'Yes',
                    'count' => $acc['true'],
                    'percentage' => $total > 0 ? round(($acc['true'] / $total) * 100, 1) : 0,
                ],
                [
                    'value' => 'No',
                    'count' => $acc['false'],
                    'percentage' => $total > 0 ? round(($acc['false'] / $total) * 100, 1) : 0,
                ],
            ],
        ];
    }

    private function formatTextList(array $acc, string $fieldType = '', int $formId = 0): array
    {
        $values = $this->generateSignedFileUrls($acc['values'], $fieldType, $formId);

        return [
            'values' => $values,
            'displayed_count' => count($values),
            'total_count' => $acc['answered'],
            'has_more' => $acc['answered'] > self::TEXT_LIST_LIMIT,
            'next_offset' => self::TEXT_LIST_LIMIT,
        ];
    }

    /**
     * Generate signed URLs for file types (files, signature)
     * Values are objects: {value: string, submission_id: int}
     */
    private function generateSignedFileUrls(array $values, string $fieldType, int $formId): array
    {
        if (!in_array($fieldType, ['files', 'signature'], true) || $formId <= 0) {
            return $values;
        }

        return array_map(function ($item) use ($formId) {
            if (empty($item['value'])) {
                return $item;
            }

            $item['value'] = URL::signedRoute(
                'open.forms.submissions.file',
                [$formId, $item['value']],
                now()->addMinutes(10)
            );

            return $item;
        }, $values);
    }

    private function formatDateSummary(array $acc): array
    {
        if (empty($acc['dates'])) {
            return ['earliest' => null, 'latest' => null, 'count' => 0];
        }

        $sorted = collect($acc['dates'])->sort()->values();

        return [
            'earliest' => $sorted->first(),
            'latest' => $sorted->last(),
            'count' => count($acc['dates']),
        ];
    }

    private function formatMatrix(array $acc, array $property): array
    {
        $rowsData = [];

        foreach ($acc['rows'] as $row => $columns) {
            arsort($columns);
            $rowTotal = array_sum($columns);

            $rowsData[$row] = [
                'distribution' => collect($columns)->map(function ($count, $column) use ($rowTotal) {
                    return [
                        'value' => $column,
                        'count' => $count,
                        'percentage' => $rowTotal > 0 ? round(($count / $rowTotal) * 100, 1) : 0,
                    ];
                })->values()->toArray(),
            ];
        }

        return ['rows' => $rowsData];
    }

    private function formatPayment(array $acc): array
    {
        return [
            'total_amount' => round($acc['total_amount'], 2),
            'transaction_count' => $acc['answered'],
            'average_amount' => $acc['answered'] > 0
                ? round($acc['total_amount'] / $acc['answered'], 2)
                : 0,
        ];
    }

    /**
     * Get additional text values for "Load More" functionality
     */
    public function getFieldTextValues(
        Form $form,
        string $fieldId,
        int $offset = 0,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $status = 'completed'
    ): ?array {
        // Find the field property
        $property = collect($form->properties)->firstWhere('id', $fieldId);

        if (!$property) {
            return null;
        }

        $query = $this->buildBaseQuery($form, $dateFrom, $dateTo, $status);

        // Get submissions with this field value
        $submissions = $query
            ->select(['id', 'data', 'created_at'])
            ->orderByDesc('created_at')
            ->get();

        // Extract values for this field
        $allValues = [];
        foreach ($submissions as $submission) {
            $data = $submission->data ?? [];
            if (!is_array($data)) {
                continue;
            }

            $value = $data[$fieldId] ?? null;
            if ($this->hasValue($value)) {
                // Handle arrays (e.g., file uploads with multiple files)
                if (is_array($value) && !$this->isAssociativeArray($value)) {
                    foreach ($value as $item) {
                        $stringValue = $this->extractStringValue($item);
                        if ($stringValue !== null) {
                            $allValues[] = [
                                'value' => $stringValue,
                                'submission_id' => $submission->id,
                            ];
                        }
                    }
                } else {
                    $stringValue = $this->extractStringValue($value);
                    if ($stringValue !== null) {
                        $allValues[] = [
                            'value' => $stringValue,
                            'submission_id' => $submission->id,
                        ];
                    }
                }
            }
        }

        $totalCount = count($allValues);
        $paginatedValues = array_slice($allValues, $offset, self::TEXT_LIST_LIMIT);

        // Generate signed URLs for file types
        $fieldType = $property['type'] ?? '';
        $paginatedValues = $this->generateSignedFileUrls($paginatedValues, $fieldType, $form->id);

        return [
            'values' => $paginatedValues,
            'displayed_count' => count($paginatedValues),
            'total_count' => $totalCount,
            'has_more' => ($offset + self::TEXT_LIST_LIMIT) < $totalCount,
            'next_offset' => $offset + self::TEXT_LIST_LIMIT,
        ];
    }

    private function getSummaryType(string $fieldType): string
    {
        if (empty($fieldType) || !isset($this->blockTypes[$fieldType])) {
            return 'text_list'; // Safe default
        }

        return $this->blockTypes[$fieldType]['summary_type'] ?? 'text_list';
    }

    private function hasValue(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return false;
        }

        if (is_string($value) && (trim($value) === '' || $value === 'null')) {
            return false;
        }

        return true;
    }

    private function getInputProperties(Form $form): Collection
    {
        return collect($form->properties ?? [])->filter(function ($prop) {
            $type = $prop['type'] ?? null;

            if (!$type || !isset($this->blockTypes[$type])) {
                return false;
            }

            return ($this->blockTypes[$type]['is_input'] ?? false) === true;
        });
    }

    private function buildBaseQuery(Form $form, ?string $dateFrom, ?string $dateTo, string $status)
    {
        $query = $form->submissions();

        if ($status === 'completed') {
            $query->where('status', FormSubmission::STATUS_COMPLETED);
        } elseif ($status === 'partial') {
            $query->where('status', FormSubmission::STATUS_PARTIAL);
        }
        // 'all' = no status filter

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query;
    }

    private function getCacheKey(Form $form, ?string $dateFrom, ?string $dateTo, string $status): string
    {
        $version = $this->getFormSummaryCacheVersion($form);

        return sprintf(
            'form_summary_%d_v%d_%s_%s_%s_%d',
            $form->id,
            $version,
            $dateFrom ?? 'all',
            $dateTo ?? 'all',
            $status,
            $form->updated_at?->timestamp ?? 0 // Invalidate when form is updated
        );
    }
}
