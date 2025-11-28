<?php

namespace App\Listeners\Forms;

use App\Events\Forms\FormSaved;
use App\Service\Forms\FormSpamService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\App;

class FormSpamCheckListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(protected FormSpamService $formSpamService)
    {
    }

    public function handle(FormSaved $event): void
    {
        if (App::environment('testing')) {
            return;
        }

        $this->formSpamService->checkForm($event->form);
    }
}
