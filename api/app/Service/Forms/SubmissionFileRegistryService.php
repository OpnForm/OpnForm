<?php

namespace App\Service\Forms;

use App\Models\Forms\FormSubmission;
use App\Models\Forms\FormSubmissionFile;

class SubmissionFileRegistryService
{
    public function __construct(private SubmissionFilePathService $filePathService)
    {
    }

    public function track(FormSubmission $submission, array $data): void
    {
        $now = now();
        $rows = collect($this->filePathService->fromData($submission->form, $data))
            ->map(fn (string $path) => [
                'form_submission_id' => $submission->id,
                'path' => $path,
                'path_hash' => hash('sha256', $path),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            FormSubmissionFile::query()->insertOrIgnore($rows);
        }
    }
}
