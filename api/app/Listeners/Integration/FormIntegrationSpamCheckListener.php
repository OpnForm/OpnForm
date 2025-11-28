<?php

namespace App\Listeners\Integration;

use App\Events\Models\FormIntegrationSaved;
use App\Service\Integrations\EmailIntegrationSpamService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\App;

class FormIntegrationSpamCheckListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct()
    {
    }

    public function handle(FormIntegrationSaved $event): void
    {
        if (App::environment('testing')) {
            return;
        }

        if ($event->formIntegration->integration_id === 'email') {
            $emailIntegrationSpamService = app(EmailIntegrationSpamService::class);
            $emailIntegrationSpamService->checkForSpam($event->formIntegration->form, $event->formIntegration);
        }

        return;
    }
}
