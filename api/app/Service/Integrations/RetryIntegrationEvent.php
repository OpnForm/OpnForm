<?php

namespace App\Service\Integrations;

use App\Events\Forms\FormSubmitted;
use App\Listeners\Forms\NotifyFormSubmission;
use App\Models\Forms\FormSubmission;
use App\Models\Integration\FormIntegration;
use App\Models\Integration\FormIntegrationsEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RetryIntegrationEvent
{
    public function handle(FormIntegration $integration, int $eventId): FormIntegrationsEvent
    {
        // Reserve a durable attempt under a short lock, before any external send.
        [$handler, $attempt] = DB::transaction(function () use ($integration, $eventId) {
            $integration = FormIntegration::whereKey($integration->id)->lockForUpdate()->firstOrFail();
            $event = $integration->events()->findOrFail($eventId);
            $event->setRelation('integration', $integration);

            if ($reason = $event->retryUnavailableReason()) {
                throw ValidationException::withMessages(['event' => [$reason]]);
            }

            $submission = $integration->form->submissions()->whereKey($event->getSubmissionId())
                ->where('status', FormSubmission::STATUS_COMPLETED)->lockForUpdate()->first();
            if (!$submission) {
                throw ValidationException::withMessages(['event' => ['The completed submission is no longer available.']]);
            }

            $handler = NotifyFormSubmission::getIntegrationHandler(
                new FormSubmitted(
                    $integration->form,
                    array_merge($submission->data ?? [], ['submission_id' => $submission->id]),
                    $submission->meta ?? []
                ),
                $integration
            );
            $attempt = $integration->events()->createQuietly([
                'status' => 'processing',
                'data' => ['submission_id' => $submission->id, 'retry_of' => $event->id],
            ]);
            $attempt->setRelation('integration', $integration);

            return [$handler, $attempt];
        });

        // A crash or persistence failure leaves the committed attempt visible and blocks replay.
        return $handler->run($attempt);
    }
}
