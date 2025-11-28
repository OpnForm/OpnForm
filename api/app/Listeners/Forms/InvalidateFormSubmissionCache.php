<?php

namespace App\Listeners\Forms;

use App\Events\Forms\FormSubmitted;

class InvalidateFormSubmissionCache
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(FormSubmitted $event): void
    {
        // Clear the form's submission count cache for real-time accuracy
        $event->form->forget('submissions_count');
    }
}
