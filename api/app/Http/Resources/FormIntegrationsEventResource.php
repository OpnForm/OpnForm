<?php

namespace App\Http\Resources;

use App\Models\Forms\FormSubmission;
use App\Models\Integration\FormIntegration;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class FormIntegrationsEventResource extends JsonResource
{
    private bool $retryEligibilityResolved = false;
    private ?string $retryUnavailableReason = null;

    public function withRetryEligibility(?string $reason): self
    {
        $this->retryEligibilityResolved = true;
        $this->retryUnavailableReason = $reason;

        return $this;
    }

    public static function forIntegration(FormIntegration $integration, Collection $events)
    {
        $latestEvents = [];
        foreach ($events as $event) {
            $event->setRelation('integration', $integration);
            $submissionId = $event->getSubmissionId();
            if ($submissionId && (!isset($latestEvents[$submissionId]) || $event->id > $latestEvents[$submissionId]->id)) {
                $latestEvents[$submissionId] = $event;
            }
        }
        $availableSubmissions = $integration->form->submissions()
            ->whereIn('id', array_keys($latestEvents))->where('status', FormSubmission::STATUS_COMPLETED)
            ->pluck('id')->flip();

        return self::collection($events->map(fn ($event) => self::make($event)->withRetryEligibility(
            $event->retryUnavailableReason(
                $latestEvents[$event->getSubmissionId()] ?? null,
                $availableSubmissions->has($event->getSubmissionId())
            )
        )));
    }

    public function toArray($request)
    {
        $reason = $this->retryEligibilityResolved ? $this->retryUnavailableReason : $this->resource->retryUnavailableReason();

        return [
            'id' => $this->id,
            'date' => date('Y-m-d H:i', strtotime($this->created_at)),
            'status' => ucfirst($this->status),
            'tracking_id' => $this->tracking_id,
            'email_tracking' => $this->tracking_id !== null,
            'legacy_email' => !$this->tracking_id && $this->integration?->integration_id === 'email',
            'updated_at' => $this->updated_at?->toIso8601String(),
            'data' => $this->data,
            'can_retry' => $reason === null,
            'retry_unavailable_reason' => $reason,
        ];
    }
}
