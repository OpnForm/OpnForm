<?php

namespace App\Http\Controllers\Forms;

use App\Exports\FormSubmissionExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnswerFormRequest;
use App\Http\Requests\FormSubmissionExportRequest;
use App\Http\Resources\FormSubmissionResource;
use App\Http\Resources\ExportJobStatusResource;
use App\Jobs\Form\StoreFormSubmissionJob;
use App\Jobs\Form\ExportFormSubmissionsJob;
use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Service\Forms\FormExportService;
use App\Service\Storage\FileUploadPathService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

class FormSubmissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth', ['except' => ['submissionFile']]);
        $this->middleware('signed', ['only' => ['submissionFile']]);
    }

    public function submissions(Form $form)
    {
        $this->authorize('view', $form);

        $query = $form->submissions()->with('form');

        // Handle search parameter - search only in JSON values, not keys
        if (request()->has('search') && !empty(request()->get('search'))) {
            $searchTerm = request()->get('search');

            if (config('database.default') === 'mysql') {
                // MySQL: Use JSON_SEARCH to find values containing the search term
                $query->whereRaw("JSON_SEARCH(data, 'one', ?) IS NOT NULL", ["%{$searchTerm}%"]);
            } else {
                // PostgreSQL: Use jsonb_each_text to search only in values
                $query->whereRaw("EXISTS (
                    SELECT 1 FROM jsonb_each_text(data) AS kv(key, value) 
                    WHERE kv.value ILIKE ?
                )", ["%{$searchTerm}%"]);
            }
        }

        // Handle status filtering for pro forms with partial submissions
        if (request()->has('status') && request()->get('status') !== 'all') {
            $status = request()->get('status');
            if ($status === FormSubmission::STATUS_COMPLETED) {
                $query->where('status', '!=', FormSubmission::STATUS_PARTIAL);
            } elseif ($status === FormSubmission::STATUS_PARTIAL) {
                $query->where('status', FormSubmission::STATUS_PARTIAL);
            }
        }

        // Default ordering by created_at desc
        $query->orderByDesc('created_at');

        // Use configurable per_page, default to 50 for better performance
        $perPage = min((int) request()->get('per_page', 100), 100);

        return FormSubmissionResource::collection($query->paginate($perPage));
    }

    public function update(AnswerFormRequest $request, Form $form, $submission_id)
    {
        $submission = $form->submissions()->where('id', $submission_id)->firstOrFail();
        $submission->setRelation('form', $form);
        $this->authorize('update', $submission);

        $submissionData = $request->validated();
        $submissionData['submission_id'] = $submission->id;
        (new StoreFormSubmissionJob($form, $submissionData))->handle();

        $submission = $submission->fresh()->setRelation('form', $form);
        $data = new FormSubmissionResource($submission);

        return $this->success([
            'message' => 'Record successfully updated.',
            'data' => $data,
        ]);
    }

    // ===== EXPORT METHODS =====

    public function export(FormSubmissionExportRequest $request, Form $form, FormExportService $exportService)
    {
        $this->authorize('view', $form);

        $displayColumns = collect($request->columns)->filter(fn ($value, $key) => $value === true)->toArray();
        $submissionIds = $request->input('submissionIds', []);
        $submissionCount = !empty($submissionIds) ? count($submissionIds) : null;

        // Check if we should process asynchronously
        if ($exportService->shouldExportAsync($form, $submissionCount)) {
            return $this->startAsyncExport($form, $displayColumns, $submissionIds, $exportService);
        }

        // Process synchronously for small exports
        return $this->processSyncExport($form, $displayColumns, $submissionIds, $exportService);
    }

    public function exportStatus(Form $form, string $jobId, FormExportService $exportService)
    {
        $this->authorize('view', $form);

        $cacheKey = $exportService->getCacheKey($jobId);
        $jobData = Cache::get($cacheKey);

        if (!$jobData) {
            return $this->error([
                'message' => 'Export job not found or has expired.'
            ], 404);
        }

        // Add job_id to the response data
        $jobData['job_id'] = $jobId;

        return new ExportJobStatusResource($jobData);
    }

    private function startAsyncExport(Form $form, array $displayColumns, array $submissionIds, FormExportService $exportService)
    {
        $jobId = $exportService->initializeAsyncExport($form, Auth::id());

        ExportFormSubmissionsJob::dispatch($form, $displayColumns, $submissionIds, $jobId, Auth::id());

        return $this->success([
            'message' => 'Export started. Large export will be processed in the background.',
            'job_id' => $jobId,
            'is_async' => true
        ]);
    }

    private function processSyncExport(Form $form, array $displayColumns, array $submissionIds, FormExportService $exportService)
    {
        $allRows = [];
        // Use query builder with orderBy for consistency with async export
        $submissionQuery = $form->submissions()->orderByDesc('created_at');
        if (!empty($submissionIds)) {
            $submissionQuery->whereIn('id', $submissionIds);
        }

        foreach ($submissionQuery->get() as $submission) {
            $allRows[] = $exportService->formatSubmissionForExport($form, $submission, $displayColumns);
        }

        $csvExport = (new FormSubmissionExport($allRows));

        return Excel::download(
            $csvExport,
            $form->slug . '-submission-data.csv',
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public function submissionFile(Form $form, $filename)
    {
        $fileName = FileUploadPathService::getFileUploadPath($form->id, urldecode($filename));

        if (! Storage::exists($fileName)) {
            return $this->error([
                'message' => 'File not found.',
            ], 404);
        }

        if (config('filesystems.default') !== 's3') {
            return response()->download(
                Storage::path($fileName),
                basename($fileName),
                [
                    'Content-Type' => 'application/octet-stream',
                    'X-Content-Type-Options' => 'nosniff',
                    'Content-Disposition' => 'attachment; filename="' . basename($fileName) . '"'
                ]
            );
        }

        // Force download on S3 as well
        return redirect(
            Storage::temporaryUrl(
                $fileName,
                now()->addMinute(),
                [
                    'ResponseContentDisposition' => 'attachment; filename="' . basename($fileName) . '"',
                    'ResponseContentType' => 'application/octet-stream'
                ]
            )
        );
    }

    public function destroy(Form $form, $submission_id)
    {
        $this->authorize('delete', $form);

        $submission = $form->submissions()->where('id', $submission_id)->firstOrFail();
        $submission->delete();

        return $this->success([
            'message' => 'Record successfully removed.',
        ]);
    }

    public function destroyMulti(Request $request, Form $form)
    {
        $request->validate([
            'submissionIds' => 'required|array',
            'submissionIds.*' => 'required|integer',
            'submissionIds.*' => 'exists:form_submissions,id',
        ]);

        $this->authorize('delete', $form);

        $submissionIds = $request->submissionIds;
        $form->submissions()
            ->whereIn('id', $submissionIds)
            ->chunk(100, function ($submissions) {
                foreach ($submissions as $submission) {
                    $submission->delete();
                }
            });

        return $this->success([
            'message' => 'Records successfully removed.',
        ]);
    }
}
