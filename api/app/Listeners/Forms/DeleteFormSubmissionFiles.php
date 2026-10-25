<?php

namespace App\Listeners\Forms;

use App\Events\Models\FormSubmissionDeleting;
use App\Service\Forms\StageFormSubmissionFileDeletions;

class DeleteFormSubmissionFiles
{
    public function __construct(private StageFormSubmissionFileDeletions $stageFileDeletions)
    {
    }

    public function handle(FormSubmissionDeleting $event): void
    {
        $this->stageFileDeletions->execute($event->submission);
    }
}
