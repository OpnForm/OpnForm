<?php

namespace App\Listeners\Forms;

use App\Events\Models\FormSubmissionSaved;
use App\Service\Forms\SubmissionFileRegistryService;

class TrackFormSubmissionFiles
{
    public function __construct(private SubmissionFileRegistryService $fileRegistry)
    {
    }

    public function handle(FormSubmissionSaved $event): void
    {
        $this->fileRegistry->track($event->submission, $event->submission->data ?? []);
    }
}
