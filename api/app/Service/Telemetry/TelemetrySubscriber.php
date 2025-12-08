<?php

namespace App\Service\Telemetry;

use App\Events\Forms\FormSubmitted;
use App\Events\Models\FormCreated;
use App\Events\Models\UserCreated;
use App\Events\Models\WorkspaceCreated;
use Illuminate\Events\Dispatcher;

class TelemetrySubscriber
{
    /**
     * Register the listeners for the subscriber.
     *
     * @param Dispatcher $events
     * @return void
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(FormCreated::class, [self::class, 'handleEvent']);
        $events->listen(WorkspaceCreated::class, [self::class, 'handleEvent']);
        $events->listen(UserCreated::class, [self::class, 'handleEvent']);
        $events->listen(FormSubmitted::class, [self::class, 'handleEvent']);
    }

    /**
     * Handle all telemetry events.
     *
     * @param mixed $event
     * @return void
     */
    public function handleEvent(mixed $event): void
    {
        $telemetryEvent = match (true) {
            $event instanceof FormCreated => TelemetryEvent::FORM_CREATED,
            $event instanceof WorkspaceCreated => TelemetryEvent::WORKSPACE_CREATED,
            $event instanceof UserCreated => TelemetryEvent::USER_CREATED,
            $event instanceof FormSubmitted => TelemetryEvent::FORM_SUBMISSION,
            default => null,
        };

        if ($telemetryEvent) {
            telemetry($telemetryEvent);
        }
    }
}
