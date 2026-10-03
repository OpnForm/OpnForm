<?php

namespace App\Service\Forms;

use App\Jobs\Form\ExportFormSubmissionsJob;
use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\User;
use App\Service\Billing\Feature;
use App\Service\Billing\PlanAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class McpSubmissionService
{
    public function __construct(
        private readonly McpFormManagementService $forms,
        private readonly FormSummaryService $summaries,
        private readonly FormExportService $exports,
        private readonly McpSubmissionExportRateLimiter $exportRateLimiter,
        private readonly FormSummaryRateLimiter $summaryRateLimiter,
        private readonly PlanAccessService $planAccess,
    ) {
    }

    /** @return array{submissions: array<int, array<string, mixed>>, pagination: array<string, int>} */
    public function list(
        User $user,
        int $formId,
        ?string $search,
        string $status,
        ?string $dateFrom,
        ?string $dateTo,
        int $page,
        int $perPage,
    ): array {
        $form = $this->forms->form($user, $formId);
        $query = $this->submissionQuery($form, $status, $dateFrom, $dateTo);

        if ($search) {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'mysql') {
                $query->whereRaw("JSON_SEARCH(data, 'one', ?) IS NOT NULL", ['%'.$search.'%']);
            } elseif ($driver === 'sqlite') {
                $query->whereRaw("EXISTS (
                    SELECT 1 FROM json_each(data)
                    WHERE CAST(json_each.value AS TEXT) LIKE ?
                )", ['%'.$search.'%']);
            } else {
                $query->whereRaw("EXISTS (
                    SELECT 1 FROM jsonb_each_text(data) AS kv(key, value)
                    WHERE kv.value ILIKE ?
                )", ['%'.$search.'%']);
            }
        }

        $submissions = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        return [
            'submissions' => collect($submissions->items())
                ->map(fn (FormSubmission $submission) => $this->serialize($form, $submission))
                ->all(),
            'pagination' => [
                'page' => $submissions->currentPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
                'last_page' => $submissions->lastPage(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function get(User $user, int $formId, int $submissionId): array
    {
        $form = $this->forms->form($user, $formId);
        $submission = $form->submissions()->find($submissionId);

        if (! $submission) {
            throw ValidationException::withMessages([
                'submission_id' => ['Submission not found or not accessible.'],
            ]);
        }

        return $this->serialize($form, $submission);
    }

    /** @return array<string, mixed> */
    public function stats(
        User $user,
        int $formId,
        string $status,
        ?string $dateFrom,
        ?string $dateTo,
    ): array {
        $form = $this->forms->form($user, $formId);
        $this->planAccess->requireFeature($form->workspace, Feature::FORM_ANALYTICS);
        $this->planAccess->requireFeature($form->workspace, Feature::FORM_SUMMARY);
        if (! $this->summaryRateLimiter->attempt($user->id)) {
            throw ValidationException::withMessages([
                'form_id' => ['Too many submission statistics requests. Try again later.'],
            ]);
        }
        $filtered = $this->submissionQuery($form, $status, $dateFrom, $dateTo);
        $filteredCount = (clone $filtered)->count();
        $averageDuration = (clone $filtered)->whereNotNull('completion_time')->avg('completion_time');
        $views = $form->views_count;
        $completed = $form->submissions()->where('status', FormSubmission::STATUS_COMPLETED)->count();
        $partial = $form->submissions()->where('status', FormSubmission::STATUS_PARTIAL)->count();

        return [
            'form_id' => $form->id,
            'filters' => [
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'overview' => [
                'views' => $views,
                'completed_submissions' => $completed,
                'partial_submissions' => $partial,
                'completion_rate' => $views > 0 ? round(($completed / $views) * 100, 2) : 0,
            ],
            'filtered_submissions' => $filteredCount,
            'average_completion_seconds' => $averageDuration !== null ? round((float) $averageDuration, 2) : null,
            'field_summary' => $this->summaries->generateSummary($form, $dateFrom, $dateTo, $status),
        ];
    }

    /** @return array<string, mixed> */
    public function startExport(User $user, int $formId, array $submissionIds): array
    {
        $form = $this->forms->form($user, $formId);

        if ($submissionIds !== []) {
            $matched = $form->submissions()->whereIn('id', $submissionIds)->count();

            if ($matched !== count(array_unique($submissionIds))) {
                throw ValidationException::withMessages([
                    'submission_ids' => ['One or more submissions were not found in this form.'],
                ]);
            }
        } elseif (! $form->submissions()->exists()) {
            throw ValidationException::withMessages([
                'form_id' => ['This form has no submissions to export.'],
            ]);
        }

        $this->exportRateLimiter->hit($user->id, $form->id);

        $columns = collect($form->properties)
            ->filter(fn (array $property) => isset($property['id']) && ! str_starts_with((string) ($property['type'] ?? ''), 'nf-'))
            ->mapWithKeys(fn (array $property) => [$property['id'] => true])
            ->put('created_at', true)
            ->all();
        $jobId = $this->exports->initializeAsyncExport($form, $user->id);

        ExportFormSubmissionsJob::dispatch($form, $columns, $submissionIds, $jobId, $user->id);

        return [
            'job_id' => $jobId,
            'status' => 'queued',
            'message' => 'Submission export queued. Poll get_submission_export until it is completed or failed.',
        ];
    }

    /** @return array<string, mixed> */
    public function exportStatus(User $user, int $formId, string $jobId): array
    {
        $form = $this->forms->form($user, $formId);
        $job = Cache::get($this->exports->getCacheKey($jobId));

        if (! is_array($job)
            || (int) ($job['user_id'] ?? 0) !== $user->id
            || (int) ($job['form_id'] ?? 0) !== $form->id) {
            throw ValidationException::withMessages([
                'job_id' => ['Export not found, expired, or not accessible.'],
            ]);
        }

        return [
            'job_id' => $jobId,
            'status' => $job['status'],
            'progress' => $job['progress'],
            'processed_submissions' => $job['processed_submissions'] ?? null,
            'total_submissions' => $job['total_submissions'] ?? null,
            'file_url' => $job['file_url'] ?? null,
            'expires_at' => $job['expires_at'] ?? null,
            'error_message' => $job['error_message'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function serialize(Form $form, FormSubmission $submission): array
    {
        $responses = (new FormSubmissionFormatter($form, $submission->data ?? []))
            ->showHiddenFields()
            ->showRemovedFields()
            ->useSignedUrlForFiles()
            ->getCleanKeyValue();

        $result = [
            'id' => $submission->id,
            'form_id' => $form->id,
            'status' => $submission->status,
            'created_at' => $submission->created_at?->toISOString(),
            'updated_at' => $submission->updated_at?->toISOString(),
            'completion_time_seconds' => $submission->completion_time,
            'responses' => $responses,
        ];

        if ($form->enable_ip_tracking
            && $form->workspace->hasFeature('enable_ip_tracking')
            && ! empty($submission->meta['ip_address'] ?? null)) {
            $result['ip_address'] = $submission->meta['ip_address'];
        }

        return $result;
    }

    private function submissionQuery(
        Form $form,
        string $status,
        ?string $dateFrom,
        ?string $dateTo,
    ): Builder {
        return $form->submissions()->getQuery()
            ->when($status === 'completed', fn (Builder $query) => $query->where('status', FormSubmission::STATUS_COMPLETED))
            ->when($status === 'partial', fn (Builder $query) => $query->where('status', FormSubmission::STATUS_PARTIAL))
            ->when($dateFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $dateTo));
    }
}
