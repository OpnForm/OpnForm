<?php

namespace App\Integrations\Handlers;

use App\Service\Integrations\EmailDeliveryTracker;
use App\Models\Integration\FormIntegrationsEvent;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use App\Notifications\Forms\FormEmailNotification;

trait TracksEmailIntegration
{
    private ?string $emailTrackingId = null;
    private bool $emailSendAttempted = false;

    public function run(?FormIntegrationsEvent $attempt = null): FormIntegrationsEvent
    {
        $this->emailTrackingId = (string) Str::uuid();
        $attributes = [
            'tracking_id' => $this->emailTrackingId,
            'status' => 'processing',
            'data' => [
                'submission_id' => $this->submissionData['submission_id'] ?? null,
                'notion_page_id' => $this->submissionData['notion_page_id'] ?? null,
                'email' => ['version' => 1, 'recipients' => []],
            ],
        ];
        if ($attempt) {
            $attributes['data'] = array_merge((array) $attempt->data, $attributes['data']);
            $attempt->fill($attributes)->saveQuietly();
            $integrationEvent = $attempt;
        } else {
            $integrationEvent = $this->formIntegration->events()->createQuietly($attributes);
        }
        try {
            $this->handle();
        } catch (\Throwable $e) {
            try {
                app(EmailDeliveryTracker::class)->outcome(
                    $this->emailTrackingId,
                    $this->emailSendAttempted ? 'unknown' : 'error',
                    $this->emailSendAttempted
                        ? 'Email processing was interrupted. Review recipient outcomes; do not retry blindly.'
                        : 'The application could not prepare the email. Contact support with this event ID.'
                );
            } catch (\Throwable $trackingException) {
                // A database outage after a possible send must not replay the notification job.
                \Illuminate\Support\Facades\Log::error('Could not persist interrupted email outcome', [
                    'tracking_id' => $this->emailTrackingId, 'exception_type' => get_class($trackingException),
                ]);
            }
            \Illuminate\Support\Facades\Log::error('Email integration processing failed', [
                'tracking_id' => $this->emailTrackingId, 'exception_type' => get_class($e),
            ]);
        }
        try {
            return $integrationEvent->refresh();
        } catch (\Throwable $exception) {
            if ($attempt) {
                throw $exception;
            }

            // Reading delivery history must not replay an ordinary notification or stop other integrations.
            \Illuminate\Support\Facades\Log::error('Could not read final email outcome', [
                'tracking_id' => $this->emailTrackingId,
                'exception_type' => get_class($exception),
            ]);

            return $integrationEvent;
        }
    }

    private function emailSkipped(string $reason): void
    {
        if ($this->emailTrackingId) {
            app(EmailDeliveryTracker::class)->outcome($this->emailTrackingId, 'skipped', $reason);
        }
    }

    private function sendTrackedEmails(array $addresses): void
    {
        $tracker = app(EmailDeliveryTracker::class);
        foreach ($tracker->prepare($this->emailTrackingId, $addresses) as $recipientId => $recipient) {
            if ($recipient['status'] === 'invalid') {
                continue;
            }
            $tracker->recipient($this->emailTrackingId, $recipientId, ['status' => 'sending']);
            try {
                $notification = new FormEmailNotification($this->event, $this->integrationData);
                $notification->emailTracking = ['event' => $this->emailTrackingId, 'recipient' => $recipientId];
                $this->emailSendAttempted = true;
                Notification::route('mail', $recipient['address'])->notify($notification);
                // If NotificationSending vetoed the send there is no NotificationSent event.
                $record = \App\Models\Integration\FormIntegrationsEvent::where('tracking_id', $this->emailTrackingId)->first();
                if (data_get($record?->data, "email.recipients.$recipientId.status") === 'sending') {
                    $tracker->recipient($this->emailTrackingId, $recipientId, ['status' => 'blocked', 'reason' => 'The application cancelled the notification.']);
                }
            } catch (\Throwable $e) {
                $tracker->failure($this->emailTrackingId, $recipientId, $e);
            }
        }
    }
}
