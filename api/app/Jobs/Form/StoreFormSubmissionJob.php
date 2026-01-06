<?php

namespace App\Jobs\Form;

use App\Events\Forms\FormSubmitted;
use App\Http\Controllers\Forms\FormController;
use App\Http\Requests\AnswerFormRequest;
use App\Service\Storage\FileUploadPathService;
use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Service\Forms\FormLogicPropertyResolver;
use App\Service\Storage\StorageFileNameParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stevebauman\Purify\Facades\Purify;

/**
 * Job to store form submissions
 *
 * This job handles the storage of form submissions, including processing of metadata
 * and special field types like files and signatures.
 *
 * The job accepts all data in the submissionData array, including metadata fields:
 * - submission_id: ID of an existing submission to update (must be an integer)
 * - completion_time: Time in seconds it took to complete the form
 * - is_partial: Whether this is a partial submission (will be stored with STATUS_PARTIAL)
 * - submitter_ip: IP address of the submitter (will be stored in meta if form has IP tracking enabled)
 *   If not specified, submissions are treated as complete by default.
 *
 * These metadata fields will be automatically extracted and removed from the stored form data.
 *
 * For partial submissions:
 * - The submission will be stored with STATUS_PARTIAL
 * - All file uploads and signatures will be processed normally
 * - The submission can later be updated to STATUS_COMPLETED when the user completes the form
 */
class StoreFormSubmissionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public ?int $submissionId = null;
    private ?array $formData = null;
    private ?int $completionTime = null;
    private bool $isPartial = false;
    private bool $isClientProvidedSubmissionId = false;
    private ?string $submitterIp = null;

    /**
     * Create a new job instance.
     *
     * @param Form $form The form being submitted
     * @param array $submissionData Form data including metadata fields (submission_id, completion_time, etc.)
     * @return void
     */
    public function __construct(public Form $form, public array $submissionData)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->extractMetadata();
        $this->formData = $this->getFormData();
        $this->addHiddenPrefills($this->formData);
        $this->storeSubmission($this->formData);
        $this->formData['submission_id'] = $this->submissionId;
        if (!$this->isPartial) {
            FormSubmitted::dispatch($this->form, $this->formData);
        }
    }

    /**
     * Extract metadata from submission data
     *
     * This method extracts and removes metadata fields from the submission data:
     * - submission_id
     * - completion_time
     * - is_partial
     * - submitter_ip
     */
    private function extractMetadata(): void
    {
        if (isset($this->submissionData['completion_time'])) {
            $this->completionTime = $this->submissionData['completion_time'];
            unset($this->submissionData['completion_time']);
        }
        if (isset($this->submissionData['submission_id']) && $this->submissionData['submission_id']) {
            if (is_numeric($this->submissionData['submission_id'])) {
                $this->submissionId = (int)$this->submissionData['submission_id'];
                $this->isClientProvidedSubmissionId = true;
            }
            unset($this->submissionData['submission_id']);
        }
        if (isset($this->submissionData['is_partial'])) {
            $this->isPartial = (bool)$this->submissionData['is_partial'];
            unset($this->submissionData['is_partial']);
        }
        if (isset($this->submissionData['submitter_ip'])) {
            $this->submitterIp = $this->submissionData['submitter_ip'];
            unset($this->submissionData['submitter_ip']);
        }
    }

    /**
     * Get the submission ID
     *
     * @return int|null
     */
    public function getSubmissionId()
    {
        return $this->submissionId;
    }

    /**
     * Resolve the record to update
     *
     * @param array $submissionData
     * @return int|null
     */
    private function resolveRecordToUpdate(array $submissionData)
    {
        if (!$this->form->is_pro || !isset($this->form->database_fields_update) || $this->submissionId) {
            return null;
        }

        $propertyIds = $this->form->database_fields_update;
        $properties = collect($this->form->properties)->filter(function ($property) use ($propertyIds) {
            return in_array($property['id'], $propertyIds);
        });

        // Build query to find record based on database_fields_update fields
        $query = $this->form->submissions();
        foreach ($properties as $property) {
            $fieldId = $property['id'];
            if (isset($submissionData[$fieldId]) && $submissionData[$fieldId]) {
                $newValue = $submissionData[$fieldId];

                // Remove country code from phone number
                if ($property['type'] == 'phone_number' && (!$property['use_simple_text_input'] ?? false)) {
                    $newValue = substr($newValue, 2);
                }

                $query->where("data->$fieldId", $newValue);
            }
        }
        $record = $query->first();

        if ($record) {
            $this->isClientProvidedSubmissionId = true;
            return $record->id;
        }
        return null;
    }

    /**
     * Store the submission in the database
     *
     * @param array $formData
     */
    private function storeSubmission(array $formData)
    {
        // Handle record update
        if ($recordToUpdate = $this->resolveRecordToUpdate($this->submissionData)) {
            $this->submissionId = $recordToUpdate;
        }

        $submission = $this->submissionId
            ? $this->form->submissions()->findOrFail($this->submissionId)
            : new FormSubmission();
        if (!$this->submissionId) {
            $submission->form_id = $this->form->id;
        }
        $submission->data = $formData;
        $submission->completion_time = $this->completionTime;

        // Determine submission status
        // Never allow a completed submission to be reverted to partial (prevents race conditions)
        if ($this->isPartial) {
            // Only set partial status if submission is new OR not already completed
            if (!$this->submissionId || $submission->status !== FormSubmission::STATUS_COMPLETED) {
                $submission->status = FormSubmission::STATUS_PARTIAL;
            }
            // If already completed, keep it completed (ignore the partial flag)
        } else {
            $submission->status = FormSubmission::STATUS_COMPLETED;
        }

        // Generate a new UUID for new submissions
        if (!$this->submissionId) {
            $submission->public_id = \Illuminate\Support\Str::uuid()->toString();
        }

        // Store IP address in meta if IP tracking is enabled
        if ($this->form->enable_ip_tracking && $this->form->is_pro && $this->submitterIp) {
            $existingMeta = $submission->meta ?? [];
            $existingMeta['ip_address'] = $this->submitterIp;
            $submission->meta = $existingMeta;
        }

        $submission->save();
        $this->submissionId = $submission->id;
    }

    /**
     * Retrieve data from request object, and pre-format it if needed.
     * - Replace notionforms id with notion field ids
     * - Clean \ in select id values
     * - Stores file and replace value with url
     * - Generate auto increment id & unique id features for rich text field
     */
    private function getFormData()
    {
        $data = $this->submissionData;
        $finalData = [];
        $properties = collect($this->form->properties);

        foreach ($data as $answerKey => $answerValue) {
            $field = $properties->where('id', $answerKey)->first();
            if (!$field) {
                continue;
            }

            // For editable submissions, always include empty values to clear fields
            // For field-matching updates, respect the form's clear_empty_fields_on_update setting
            $shouldSkipEmpty = !$this->isClientProvidedSubmissionId && !($this->form->clear_empty_fields_on_update ?? false);
            if ($shouldSkipEmpty && (empty($answerValue) || is_null($answerValue)) && $answerValue !== 0 && $answerValue !== '0' && $answerValue !== false) {
                continue;
            }


            // Sanitize only rich text; plain text fields are stored as-is and rendered safely in UI
            if ($field['type'] === 'rich_text') {
                if (is_string($answerValue)) {
                    // Ensure rich text is cleaned with strict allowlist (defense-in-depth)
                    $answerValue = Purify::clean($answerValue);
                }
            }

            if (
                ($field['type'] == 'url' && isset($field['file_upload']) && $field['file_upload'])
                || $field['type'] == 'files'
            ) {
                if (is_array($answerValue)) {
                    $processedFiles = [];
                    foreach ($answerValue as $fileItem) {
                        if (is_string($fileItem) && !empty($fileItem)) {
                            $singleStoredFile = $this->storeFile($fileItem);
                            if ($singleStoredFile) {
                                $processedFiles[] = $singleStoredFile;
                            }
                        }
                    }
                    $finalData[$field['id']] = $processedFiles;
                } else {
                    if (is_string($answerValue) && !empty($answerValue)) {
                        $singleFileResult = $this->storeFile($answerValue);
                        $finalData[$field['id']] = $singleFileResult;
                    } else {
                        $finalData[$field['id']] = $this->storeFile($answerValue); // Handles null/empty $answerValue by returning null
                    }
                }
            } else {
                // Standard field processing (text, ID generation, etc.)
                if (isset($field['generates_uuid']) && $field['generates_uuid'] && $field['type'] == 'text') {
                    if (empty($answerValue) || !Str::isUuid($answerValue)) {
                        $finalData[$field['id']] = ($this->form->is_pro) ? Str::uuid()->toString() : 'Please upgrade your OpenForm subscription to use our ID generation features';
                    } else {
                        $finalData[$field['id']] = $answerValue;
                    }
                } elseif (isset($field['generates_auto_increment_id']) && $field['generates_auto_increment_id'] && $field['type'] == 'text') {
                    if (empty($answerValue) || !is_numeric($answerValue)) {
                        $finalData[$field['id']] = ($this->form->is_pro) ? (string)($this->form->submissions_count + 1) : 'Please upgrade your OpenForm subscription to use our ID generation features';
                    } else {
                        $finalData[$field['id']] = $answerValue;
                    }
                } else {
                    $finalData[$field['id']] = $answerValue;
                }
            }
            // Special field types
            if ($field['type'] == 'signature') {
                $finalData[$field['id']] = $this->storeSignature($answerValue);
            }
            if ($field['type'] == 'phone_number' && $answerValue && ctype_alpha(substr($answerValue, 0, 2)) && (!isset($field['use_simple_text_input']) || !$field['use_simple_text_input'])) {
                $finalData[$field['id']] = substr($answerValue, 2);
            }
        }
        return $finalData;
    }

    // This is use when updating a record, and file uploads aren't changed.
    private function isSkipForUpload($value)
    {
        $parser = StorageFileNameParser::parse($value);
        $canonicalStoredName = $parser->getMovedFileName();

        if (!$canonicalStoredName) {
            return false; // Input $value couldn't be resolved to a canonical stored name format
        }

        $fullPathToCheck = FileUploadPathService::getFileUploadPath($this->form->id, $canonicalStoredName);
        return Storage::exists($fullPathToCheck);
    }

    /**
     * Custom Back-end Value formatting. Use case:
     * - File uploads (move file from tmp storage to persistent)
     *
     * File can have 2 formats:
     * - file_name-{uuid}.{ext}
     * - {uuid}
     */
    private function storeFile($value, ?bool $isPublic = null)
    {
        if (is_null($value) || empty($value)) {
            return null;
        }
        // Handle pre-existing full URLs (e.g., from prefill)
        if (filter_var($value, FILTER_VALIDATE_URL) !== false && str_contains($value, parse_url(config('app.url'))['host'])) {
            $fileName = explode('?', basename($value))[0];
            $path = FormController::ASSETS_UPLOAD_PATH . '/' . $fileName; // Assuming assets are in a defined path
            $newPath = FileUploadPathService::getFileUploadPath($this->form->id, $fileName);
            Storage::move($path, $newPath);
            return $fileName;
        }

        $shouldSkip = $this->isSkipForUpload($value);

        if ($shouldSkip) {
            // File (based on canonical name derived from $value) already exists in permanent storage.
            // Return its canonical name.
            $parser = StorageFileNameParser::parse($value);
            return $parser->getMovedFileName() ?? $value; // Fallback to $value if canonical somehow fails (defensive)
        }

        // Process as a new file upload (or one whose temp version needs to be moved)
        $fileNameParser = StorageFileNameParser::parse($value); // $value is the temp file reference (e.g., originalname_uuid.ext or uuid)

        if (!$fileNameParser || !$fileNameParser->uuid) {
            return null; // Cannot derive UUID from the reference
        }
        $fileNameInTmp = FileUploadPathService::getTmpFileUploadPath($fileNameParser->uuid);
        if (!Storage::exists($fileNameInTmp)) {
            return null; // Temporary file not found
        }
        $movedFileName = $fileNameParser->getMovedFileName(); // This is the canonical name for storage
        if (empty($movedFileName)) {
            return null; // Canonical name generation failed
        }
        $completeNewFilename = FileUploadPathService::getFileUploadPath($this->form->id, $movedFileName);
        Storage::move($fileNameInTmp, $completeNewFilename);
        return $movedFileName;
    }

    private function storeSignature(?string $value)
    {
        // If $value looks like a filename (already processed, e.g. during skip or previous handling)
        if ($value && preg_match('/^[\/\w\-. ]+$/', $value)) {
            return $this->storeFile($value); // Re-run through storeFile for consistency / skip logic
        }
        // If $value is base64 data
        if ($value == null || !isset(explode(',', $value)[1])) {
            return null;
        }
        $fileName = 'sign_' . (string) Str::uuid() . '.png';
        $completeNewFilename = FileUploadPathService::getFileUploadPath($this->form->id, $fileName);
        Storage::put($completeNewFilename, base64_decode(explode(',', $value)[1]));
        return $fileName;
    }

    /**
     * Adds prefill from hidden fields
     *
     * @param  AnswerFormRequest  $request
     */
    private function addHiddenPrefills(array &$formData): void
    {
        collect($this->form->properties)->filter(function ($property) {
            return FormLogicPropertyResolver::isHidden($property, $this->submissionData);
        })->each(function (array $property) use (&$formData) {
            // If a value is already set, we don't do anything for this property
            if (array_key_exists($property['id'], $formData) && $formData[$property['id']] !== '' && $formData[$property['id']] !== [] && !is_null($formData[$property['id']])) {
                return;
            }

            // Handle ID Generation for text fields
            if ($property['type'] == 'text') {
                if (isset($property['generates_uuid']) && $property['generates_uuid']) {
                    $formData[$property['id']] = ($this->form->is_pro) ? Str::uuid()->toString() : 'Please upgrade your OpenForm subscription to use our ID generation features';
                    return; // ID generated, so we skip prefill logic for this field.
                }

                if (isset($property['generates_auto_increment_id']) && $property['generates_auto_increment_id']) {
                    $formData[$property['id']] = ($this->form->is_pro) ? (string)($this->form->submissions_count + 1) : 'Please upgrade your OpenForm subscription to use our ID generation features';
                    return; // ID generated, so we skip prefill logic for this field.
                }
            }

            // From here, it's prefill logic.
            if (!isset($property['prefill']) || is_null($property['prefill'])) {
                return;
            }
            if (in_array($property['type'], ['files']) || ($property['type'] == 'url' && isset($property['file_upload']) && $property['file_upload'])) {
                return;
            }

            if ($property['type'] === 'date' && isset($property['prefill_today']) && $property['prefill_today']) {
                $formData[$property['id']] = now()->format((isset($property['with_time']) && $property['with_time']) ? 'Y-m-d H:i' : 'Y-m-d');
            } else {
                $formData[$property['id']] = $property['prefill'];
            }
        });
    }

    /**
     * Get the processed form data including the submission ID
     *
     * @return array
     */
    public function getProcessedData(): array
    {
        if ($this->formData === null) {
            $this->formData = $this->getFormData();
        }
        $data = $this->formData;
        $data['submission_id'] = $this->submissionId;
        return $data;
    }
}
