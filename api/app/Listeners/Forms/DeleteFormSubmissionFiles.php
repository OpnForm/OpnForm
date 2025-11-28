<?php

namespace App\Listeners\Forms;

use App\Events\Models\FormSubmissionDeleting;
use App\Service\Storage\FileUploadPathService;
use Illuminate\Support\Facades\Storage;

class DeleteFormSubmissionFiles
{
    public function handle(FormSubmissionDeleting $event): void
    {
        $submission = $event->submission;
        $data = $submission->data;

        if (empty($data)) {
            return;
        }

        $fileFieldIds = collect($submission->form->properties)
            ->filter(function ($property) {
                return in_array($property['type'], ['files', 'signature']) ||
                    ($property['type'] === 'url' && ($property['file_upload'] ?? false));
            })
            ->pluck('id')
            ->all();

        foreach ($data as $key => $value) {
            if (!$value || !in_array($key, $fileFieldIds)) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $file) {
                    if (is_string($file)) {
                        $this->deleteFile($submission->form->id, $file);
                    }
                }
            } elseif (is_string($value)) {
                $this->deleteFile($submission->form->id, $value);
            }
        }
    }

    private function deleteFile(int|string $formId, string $fileName): void
    {
        $path = FileUploadPathService::getFileUploadPath($formId, urldecode($fileName));
        if (Storage::exists($path)) {
            Storage::delete($path);
        }
    }
}
