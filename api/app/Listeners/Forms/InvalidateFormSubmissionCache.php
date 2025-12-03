<?php

namespace App\Listeners\Forms;

use App\Events\Forms\FormSubmitted;
use App\Service\Forms\FormSummaryService;

class InvalidateFormSubmissionCache
{
    public function __construct(
        private FormSummaryService $summaryService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(FormSubmitted $event): void
    {
        // Clear the form's submission count cache for real-time accuracy
        $event->form->forget('submissions_count');

        // Clear the form summary cache
        $this->summaryService->clearFormSummaryCache($event->form);
    }
}
