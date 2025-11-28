<?php

namespace App\Service\Telemetry;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTelemetryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $eventName,
        public array $properties
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(TelemetryService $telemetryService): void
    {
        if (!$telemetryService->shouldSendTelemetry()) {
            return;
        }

        try {
            $instanceId = $telemetryService->getInstanceId();

            if (!$instanceId) {
                Log::warning('Telemetry skipped: instance_id not found');
                return;
            }

            $client = $telemetryService->createClient();

            $client->sendEvent(
                $this->eventName,
                $this->properties,
                $instanceId
            );
        } catch (\Exception $e) {
            Log::warning('Telemetry job error', [
                'event' => $this->eventName,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
