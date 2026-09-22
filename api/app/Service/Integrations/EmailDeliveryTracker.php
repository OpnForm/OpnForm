<?php

namespace App\Service\Integrations;

use App\Models\Integration\FormIntegrationsEvent;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Updates the existing integration event; never sends or retries email. */
class EmailDeliveryTracker
{
    public function handle(NotificationSent $event): void
    {
        $context = $event->notification->emailTracking ?? null;
        if ($event->channel !== 'mail' || !$context) {
            return;
        }
        $message = $event->response?->getSymfonySentMessage()->getOriginalMessage();
        $sesId = $message?->getHeaders()->get('X-SES-Message-ID')?->getBodyAsString();
        $transport = $event->notification->emailTransport ?? 'unknown';
        $status = $event->response === null ? 'blocked' : (in_array($transport, ['log', 'array']) ? 'not_sent' : 'accepted');
        $this->recipient($context['event'], $context['recipient'], [
            'status' => $status,
            'provider' => $sesId ? 'ses' : $transport,
            'provider_message_id' => $sesId,
            'reason' => match ($status) {
                'blocked' => 'The application cancelled the email before transport acceptance.',
                'not_sent' => 'The configured mail transport does not deliver email.',
                default => 'Accepted by the mail transport; delivery is not yet confirmed.',
            },
        ]);
    }

    public function failure(string $id, string $recipient, \Throwable $exception): void
    {
        $code = null;
        for ($cause = $exception; $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof \Aws\Exception\AwsException) {
                $code = $cause->getAwsErrorCode();
                break;
            }
        }
        $reasons = [
            'MessageRejected' => 'SES rejected the message. Check the sender identity and account sending restrictions.',
            'MailFromDomainNotVerifiedException' => 'SES rejected the message because the MAIL FROM domain is not verified.',
            'ConfigurationSetDoesNotExistException' => 'SES rejected the message because its configuration set does not exist.',
            'AccountSendingPausedException' => 'SES email sending is paused for this account.',
            'ConfigurationSetSendingPausedException' => 'SES sending is paused for this configuration set.',
            'Throttling' => 'SES refused the send because its sending limit was exceeded.',
            'ThrottlingException' => 'SES refused the send because its sending limit was exceeded.',
            'AccessDenied' => 'SES refused the send because the application lacks permission.',
            'AccessDeniedException' => 'SES refused the send because the application lacks permission.',
        ];
        $rejected = isset($reasons[$code ?? '']);
        $this->recipient($id, $recipient, [
            'status' => $rejected ? 'rejected' : 'unknown',
            'reason' => $rejected ? $reasons[$code] : 'The send did not complete normally. Delivery is uncertain; do not retry blindly.',
            'reason_code' => $rejected ? $code : null,
            'exception_type' => class_basename($exception),
        ]);
    }

    public function prepare(string $id, array $addresses): array
    {
        $recipients = [];
        foreach (array_unique(array_filter(array_map('trim', $addresses), fn ($address) => $address !== '')) as $address) {
            $valid = (bool) filter_var($address, FILTER_VALIDATE_EMAIL);
            $recipients[(string) Str::uuid()] = [
                'address' => $address,
                'status' => $valid ? 'pending' : 'invalid',
                'reason' => $valid ? null : 'Invalid recipient address. No email was sent.',
                'updated_at' => now()->toIso8601String(),
            ];
        }
        $this->update($id, function (&$data) use ($recipients) {
            $data['email']['recipients'] = $recipients;
            if (!$recipients) {
                $data['email']['reason'] = 'No recipient address was resolved. No email was sent.';
                $data['email']['outcome'] = 'error';
            }
        });
        return $recipients;
    }

    public function recipient(string $id, string $recipient, array $changes): void
    {
        $this->update($id, function (&$data) use ($recipient, $changes) {
            if (!isset($data['email']['recipients'][$recipient])) {
                return;
            }
            $current = &$data['email']['recipients'][$recipient];
            // Acceptance is positive evidence even if a later listener or persistence step fails.
            if (($current['status'] ?? null) === 'accepted' && in_array($changes['status'] ?? null, ['unknown', 'blocked', 'rejected'])) {
                return;
            }
            if (isset($changes['status'])) {
                $current['timeline'][$changes['status']] ??= now()->toIso8601String();
            }
            // Feedback can arrive before NotificationSent. Preserve its outcome and correlation.
            if (isset($current['feedback'])) {
                unset($changes['status'], $changes['reason'], $changes['reason_code']);
                if (!empty($current['provider_message_id'])) {
                    unset($changes['provider_message_id'], $changes['provider']);
                }
            }
            $current = array_merge($current, $changes, ['updated_at' => now()->toIso8601String()]);
        });
    }

    public function outcome(string $id, string $status, string $reason, array $metadata = []): void
    {
        $this->update($id, function (&$data) use ($status, $reason, $metadata) {
            $data = array_merge($data, $metadata);
            $data['email']['outcome'] = $status;
            $data['email']['reason'] = $reason;
        });
    }

    public function feedback(array $payload): bool
    {
        $type = strtolower(is_string($payload['notificationType'] ?? $payload['eventType'] ?? null)
            ? ($payload['notificationType'] ?? $payload['eventType']) : '');
        if (!in_array($type, ['delivery', 'bounce', 'complaint'])) {
            return false;
        }
        $rules = [
            'mail' => 'required|array',
            'mail.messageId' => 'required|string|max:1024',
            'mail.headers' => 'required|array|max:200',
            'mail.headers.*' => 'required|array',
            'mail.headers.*.name' => 'required|string|max:256',
            'mail.headers.*.value' => 'present|string|max:10000',
            $type => 'required|array',
            "$type.timestamp" => 'sometimes|string|max:128',
        ];
        $list = match ($type) {
            'delivery' => 'recipients', 'bounce' => 'bouncedRecipients', 'complaint' => 'complainedRecipients',
        };
        $rules["$type.$list"] = 'required|array|max:1000';
        $rules["$type.$list.*"] = $type === 'delivery' ? 'required|string|max:320' : 'required|array';
        if ($type !== 'delivery') {
            $rules["$type.$list.*.emailAddress"] = 'required|string|max:320';
        }
        foreach (['bounceType', 'bounceSubType', 'complaintFeedbackType'] as $field) {
            $rules["$type.$field"] = 'sometimes|string|max:128';
        }
        if (\Illuminate\Support\Facades\Validator::make($payload, $rules)->fails()) {
            return false;
        }
        $headers = [];
        foreach ($payload['mail']['headers'] ?? [] as $header) {
            $headers[strtolower($header['name'] ?? '')] = $header['value'] ?? '';
        }
        $id = $headers['x-form-email-event-id'] ?? '';
        $recipientId = $headers['x-form-email-recipient-id'] ?? '';
        if (!Str::isUuid($id) || !Str::isUuid($recipientId)) {
            return false;
        }
        $section = $payload[$type] ?? [];
        $addresses = match ($type) {
            'delivery' => $section['recipients'] ?? [],
            'bounce' => array_column($section['bouncedRecipients'] ?? [], 'emailAddress'),
            'complaint' => array_column($section['complainedRecipients'] ?? [], 'emailAddress'),
            default => [],
        };
        $matched = false;
        $this->update($id, function (&$data) use ($recipientId, $type, $section, $addresses, $payload, &$matched) {
            if (!isset($data['email']['recipients'][$recipientId])) {
                return;
            }
            $recipient = &$data['email']['recipients'][$recipientId];
            if (!in_array($recipient['address'], $addresses, true)) {
                return;
            }
            $messageId = $payload['mail']['messageId'] ?? null;
            if (!$messageId || (!empty($recipient['provider_message_id']) && $recipient['provider_message_id'] !== $messageId)) {
                return;
            }
            $matched = true;
            if (isset($recipient['feedback'][$type])) {
                return;
            }
            // One immutable fact per feedback type. Duplicates and out-of-order delivery are harmless.
            $recipient['feedback'][$type] ??= [
                'at' => $section['timestamp'] ?? null,
                'received_at' => now()->toIso8601String(),
                'type' => $type === 'bounce' ? ($section['bounceType'] ?? null) : ($section['complaintFeedbackType'] ?? null),
                'subtype' => $section['bounceSubType'] ?? null,
            ];
            $recipient['provider'] = 'ses';
            $recipient['provider_message_id'] = $messageId;
            $recipient['status'] = isset($recipient['feedback']['complaint']) ? 'complained' : (isset($recipient['feedback']['bounce']) ? 'bounced' : 'delivered');
            $recipient['reason'] = match ($recipient['status']) {
                'complained' => 'The recipient reported this email as spam.',
                'bounced' => 'Email delivery failed. Check the bounce type before sending again.',
                default => 'Accepted by the receiving mail server. Inbox placement is not guaranteed.',
            };
            $recipient['updated_at'] = now()->toIso8601String();
        });
        return $matched;
    }

    private function update(string $id, callable $change): void
    {
        DB::transaction(function () use ($id, $change) {
            $event = FormIntegrationsEvent::where('tracking_id', $id)->lockForUpdate()->first();
            if (!$event) {
                return; // The integration or its retained history may have been deleted.
            }
            $data = json_decode(json_encode($event->data), true);
            $before = $data;
            $change($data);
            if ($data === $before) {
                return;
            }
            $statuses = array_column($data['email']['recipients'] ?? [], 'status');
            $status = $data['email']['outcome'] ?? 'processing';
            if ($statuses) {
                $status = match (true) {
                    (bool) array_intersect($statuses, ['invalid', 'blocked', 'not_sent', 'rejected', 'bounced', 'complained']) => 'error',
                    in_array('unknown', $statuses) || (
                        ($data['email']['outcome'] ?? null) === 'unknown'
                        && array_intersect($statuses, ['pending', 'sending'])
                    ) => 'unknown',
                    (bool) array_intersect($statuses, ['pending', 'sending']) => 'processing',
                    count(array_unique($statuses)) === 1 && $statuses[0] === 'delivered' => 'delivered',
                    default => 'accepted',
                };
            }
            $notifyFailure = $status === 'error' && empty($data['email']['failure_notification_attempted']);
            if ($notifyFailure) {
                $data['email']['failure_notification_attempted'] = true;
            }
            $event->update(['data' => $data, 'status' => $status]);
            if ($notifyFailure) {
                DB::afterCommit(function () use ($event) {
                    try {
                        \App\Events\Models\FormIntegrationsEventCreated::dispatch($event);
                    } catch (\Throwable $exception) {
                        // Alert delivery must never interrupt the actual recipient sends or SNS acknowledgement.
                        \Illuminate\Support\Facades\Log::error('Integration failure alert could not be queued', [
                            'tracking_id' => $event->tracking_id,
                            'exception_type' => get_class($exception),
                        ]);
                    }
                });
            }
        });
    }
}
