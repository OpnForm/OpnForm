<?php

namespace App\Models\Integration;

use App\Events\Models\FormIntegrationsEventCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormIntegrationsEvent extends Model
{
    use HasFactory;

    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'integration_id',
        'tracking_id',
        'status',
        'data'
    ];

    protected function casts()
    {
        return [
            'data' => 'object'
        ];
    }

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'created' => FormIntegrationsEventCreated::class,
    ];

    public function integration()
    {
        return $this->belongsTo(FormIntegration::class, 'integration_id');
    }

    public function getSubmissionId(): ?int
    {
        $id = filter_var(data_get($this->data, 'submission_id'), FILTER_VALIDATE_INT);

        return $id !== false && $id > 0 ? $id : null;
    }

    public function canRetry(): bool
    {
        return $this->retryUnavailableReason() === null;
    }

    public function retryUnavailableReason(?self $latestEvent = null, ?bool $submissionAvailable = null): ?string
    {
        if ($this->status !== self::STATUS_ERROR) {
            return 'Only failed events can be retried.';
        }

        $submissionId = $this->getSubmissionId();
        if (!$submissionId) {
            return 'This event cannot be retried because submission data is unavailable.';
        }

        $integration = $this->integration;
        if (!$integration || $integration->status !== FormIntegration::STATUS_ACTIVE) {
            return 'Activate this integration before retrying an event.';
        }

        $latestEvent ??= $integration->events()->where('data->submission_id', $submissionId)->orderByDesc('id')->first();
        if ($latestEvent && $latestEvent->id !== $this->id) {
            return in_array($latestEvent->status, [self::STATUS_SUCCESS, 'accepted', 'delivered'], true)
                ? 'This event has already been successfully retried.'
                : 'A more recent attempt exists for this submission. Refresh the history to review it.';
        }

        $submissionAvailable ??= $integration->form->submissions()
            ->whereKey($submissionId)->where('status', \App\Models\Forms\FormSubmission::STATUS_COMPLETED)->exists();
        if (!$submissionAvailable) {
            return 'This event cannot be retried because the completed submission is no longer available.';
        }

        if ($integration->integration_id === 'email') {
            if (!$this->tracking_id || data_get($this->data, 'email.version') !== 1) {
                return 'Delivery details are unavailable for this email. Review its delivery before sending again.';
            }

            $recipients = data_get($this->data, 'email.recipients');
            if (!is_array($recipients) && !is_object($recipients)) {
                return 'Email recipient outcomes are unavailable. Review delivery before sending again.';
            }
            if (data_get($this->data, 'email.outcome') === 'unknown') {
                return 'Email delivery is uncertain. Review recipient outcomes before sending again.';
            }
            foreach ($recipients as $recipient) {
                if (!in_array(data_get($recipient, 'status'), ['invalid', 'blocked', 'not_sent', 'rejected'], true)) {
                    return 'Some recipients may already have received this email. Review recipient outcomes before sending again.';
                }
            }
        }

        return null;
    }
}
