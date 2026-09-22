<?php

namespace App\Integrations\Handlers;

use App\Service\Integrations\EmailDeliveryTracker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use App\Notifications\Forms\FormEmailNotification;

trait TracksEmailIntegration
{
    private ?string $emailTrackingId = null;

    public function run(): void
    {
        $this->emailTrackingId = (string) Str::uuid();
        $this->formIntegration->events()->createQuietly([
            'tracking_id' => $this->emailTrackingId,
            'status' => 'processing',
            'data' => [
                'submission_id' => $this->submissionData['submission_id'] ?? null,
                'notion_page_id' => $this->submissionData['notion_page_id'] ?? null,
                'email' => ['version' => 1, 'recipients' => []],
            ],
        ]);
        try {
            $this->handle();
        } catch (\Throwable $e) {
            // Before recipient dispatch: do not expose SMTP credentials or arbitrary provider payloads.
            app(EmailDeliveryTracker::class)->outcome($this->emailTrackingId, 'error', 'The application could not prepare the email. Contact support with this event date.');
            \Illuminate\Support\Facades\Log::error('Email integration preparation failed', [
                'tracking_id' => $this->emailTrackingId, 'exception_type' => get_class($e),
            ]);
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
