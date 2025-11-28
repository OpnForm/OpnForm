<?php

namespace App\Events\Models;

use App\Models\Integration\FormIntegration;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormIntegrationSaved
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public FormIntegration $formIntegration
    ) {
    }
}
