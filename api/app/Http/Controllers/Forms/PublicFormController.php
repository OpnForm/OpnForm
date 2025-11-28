<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnswerFormRequest;
use App\Models\Forms\Form;
use App\Http\Resources\FormResource;
use App\Http\Resources\FormSubmissionResource;
use App\Jobs\Form\StoreFormSubmissionJob;
use App\Service\Forms\Analytics\UserAgentHelper;
use App\Service\Forms\FormSubmissionProcessor;
use App\Service\Forms\FormCleaner;
use App\Service\WorkspaceHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Support\Str;

class PublicFormController extends Controller
{
    public function show(Request $request, Form $form)
    {
        // Ensure form is public or closed
        if (!in_array($form->visibility, ['public', 'closed'])) {
            abort(404);
        }
        if ($form->workspace == null) {
            // Workspace deleted
            return $this->error([
                'message' => 'Form not found.',
            ], 404);
        }

        $formCleaner = new FormCleaner();

        // Disable pro features if needed
        $form->fill(
            $formCleaner
                ->processForm($request, $form)
                ->performCleaning($form->workspace)
                ->getData()
        );

        return (new FormResource($form))
            ->setCleanings($formCleaner->getPerformedCleanings());
    }

    public function view(Request $request, Form $form)
    {
        // Ensure form is public
        if ($form->visibility !== 'public') {
            abort(404);
        }

        if ($form->workspace == null) {
            return $this->error([
                'message' => 'Form not found.',
            ], 404);
        }

        if (Auth::check()) {
            return $this->success([
                'message' => 'Form viewed by logged in user.',
            ]);
        }

        // Increment view count and store metadata for analytics
        $userAgent = new UserAgentHelper($request);
        $form->views()->create(['meta' => $userAgent->getMetadata()]);

        return $this->success([
            'message' => 'Form viewed.',
        ]);
    }

    public function listUsers(Request $request, Form $form)
    {
        // Check that form has user field
        if (!$form->has_user_field) {
            return [];
        }

        // Use serializer
        $workspace = $form->workspace;

        return (new WorkspaceHelper($workspace))->getAllUsers();
    }

    public function showAsset($assetFileName)
    {
        $path = FormController::ASSETS_UPLOAD_PATH . '/' . $assetFileName;
        if (!Storage::exists($path)) {
            return $this->error([
                'message' => 'File not found.',
                'file_name' => $assetFileName,
            ]);
        }

        $internal_url = Storage::temporaryUrl($path, now()->addMinutes(5));

        foreach (config('filesystems.disks.s3.temporary_url_rewrites') as $from => $to) {
            $internal_url = str_replace($from, $to, $internal_url);
        }

        return redirect()->to($internal_url);
    }

    /**
     * Handle partial form submissions
     *
     * @param array $submissionData
     * @return \Illuminate\Http\JsonResponse
     */
    private function handlePartialSubmissions(array $submissionData, Form $form)
    {
        // Validate that at least one field has a value
        $hasValue = false;
        foreach ($submissionData as $key => $value) {
            if (Str::isUuid($key) && !empty($value)) {
                $hasValue = true;
                break;
            }
        }
        if (!$hasValue) {
            return $this->error([
                'message' => 'At least one field must have a value for partial submissions.'
            ], 422);
        }

        $submissionData['is_partial'] = true;
        $job = new StoreFormSubmissionJob($form, $submissionData);
        $job->handle();

        return $this->success([
            'message' => 'Partial submission saved',
            'submission_hash' => Hashids::encode($job->getSubmissionId())
        ]);
    }

    public function answer(AnswerFormRequest $request, Form $form, FormSubmissionProcessor $formSubmissionProcessor)
    {
        // Check if user can answer this form
        $this->authorize('answer', $form);

        $isFirstSubmission = ($form->submissions_count === 0);

        // Check for partial submission flag early (before validation)
        $isPartial = $request->get('is_partial') ?? false;

        // Use raw data for partial submissions (don't validate all required fields)
        // Use validated data for complete submissions
        $submissionData = ($isPartial && $form->enable_partial_submissions && $form->is_pro)
            ? $request->all()
            : $request->validated();

        // Process submission hash and ID
        $submissionData = $this->processSubmissionIdentifiers($request, $submissionData);

        // Add IP address for tracking if enabled
        unset($submissionData['submitter_ip']);
        if ($form->enable_ip_tracking && $form->is_pro) {
            $submissionData['submitter_ip'] = $request->ip();
        }

        // Handle partial submissions
        if ($isPartial && $form->enable_partial_submissions && $form->is_pro) {
            return $this->handlePartialSubmissions($submissionData, $form);
        }

        // Create the job with all data (including metadata)
        $job = new StoreFormSubmissionJob($form, $submissionData);

        // Process the submission
        if ($formSubmissionProcessor->shouldProcessSynchronously($form)) {
            $job->handle();
            $encodedSubmissionId = Hashids::encode($job->getSubmissionId());
            // Update submission data with generated values for redirect URL
            $submissionData = $job->getProcessedData();
        } else {
            dispatch($job);
        }

        // Return the response
        return $this->success(array_merge([
            'message' => 'Form submission saved.',
            'submission_id' => $encodedSubmissionId ?? null,
            'is_first_submission' => $isFirstSubmission,
        ], $formSubmissionProcessor->getRedirectData($form, $submissionData)));
    }

    /**
     * Processes submission identifiers to ensure consistent numeric format
     *
     * Takes a submission hash or string ID and converts it to a numeric submission_id.
     * This allows submissions to be identified by either a hashed value or direct ID
     * while ensuring consistent internal storage format.
     *
     * @param Request $request
     * @param array $submissionData
     * @return array
     */
    private function processSubmissionIdentifiers(Request $request, array $submissionData): array
    {
        // Handle submission hash if present (convert to numeric submission_id)
        $submissionHash = $request->get('submission_hash');
        if ($submissionHash) {
            $decodedHash = Hashids::decode($submissionHash);
            if (!empty($decodedHash)) {
                $submissionData['submission_id'] = (int)($decodedHash[0] ?? null);
            }
            unset($submissionData['submission_hash']);
        }

        // Handle string submission_id if present (convert to numeric)
        if (isset($submissionData['submission_id']) && is_string($submissionData['submission_id']) && !is_numeric($submissionData['submission_id'])) {
            $decodedId = Hashids::decode($submissionData['submission_id']);
            if (!empty($decodedId)) {
                $submissionData['submission_id'] = (int)($decodedId[0] ?? null);
            }
        }

        return $submissionData;
    }

    public function fetchSubmission(Request $request, Form $form, string $submission_id)
    {
        // Decode the submission ID using the same approach as in processSubmissionIdentifiers
        $decodedId = Hashids::decode($submission_id);
        $submissionId = !empty($decodedId) ? (int)($decodedId[0]) : false;

        // Ensure form is public and allows editable submissions
        if ($form->visibility !== 'public') {
            abort(404);
        }
        if ($form->workspace == null || !$form->editable_submissions || !$submissionId) {
            return $this->error([
                'message' => 'Not allowed.',
            ]);
        }

        $submission = $form->submissions()->find($submissionId);
        if (!$submission) {
            return $this->error([
                'message' => 'Submission not found.',
            ]);
        }

        $submission->setRelation('form', $form);
        $resource = new FormSubmissionResource($submission);
        $resource->publiclyAccessed();

        return $this->success($resource->toArray($request));
    }
}
