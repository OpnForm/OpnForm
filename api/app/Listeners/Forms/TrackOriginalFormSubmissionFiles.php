<?php

namespace App\Listeners\Forms;

use App\Events\Models\FormSubmissionUpdating;
use App\Service\Forms\SubmissionFileRegistryService;

class TrackOriginalFormSubmissionFiles
{
    public function __construct(private SubmissionFileRegistryService $fileRegistry)
    {
    }

    public function handle(FormSubmissionUpdating $event): void
    {
        $originalData = $event->submission->getRawOriginal('data');

        if (is_string($originalData)) {
            $originalData = json_decode($originalData, true);
        }

        if (is_array($originalData)) {
            $this->fileRegistry->track($event->submission, $originalData);
        }
    }
}
