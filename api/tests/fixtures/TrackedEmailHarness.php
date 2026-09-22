<?php

namespace Tests\Fixtures;

use App\Models\Integration\FormIntegration;

class TrackedEmailHarness
{
    use \App\Integrations\Handlers\TracksEmailIntegration;

    public array $submissionData = [];
    public object $integrationData;
    public \App\Events\Forms\FormSubmitted $event;

    public function __construct(public FormIntegration $formIntegration)
    {
        $this->integrationData = (object) [];
        $this->event = new \App\Events\Forms\FormSubmitted(new \App\Models\Forms\Form(), []);
    }

    public function handle(): void
    {
        $this->sendTrackedEmails(['person@example.com', 'other@example.com']);
    }
}
