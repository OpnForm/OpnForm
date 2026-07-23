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
        $data = (array) ($this->data ?? []);

        return isset($data['submission_id']) ? (int) $data['submission_id'] : null;
    }

    public function canRetry(?iterable $siblingEvents = null): bool
    {
        if ($this->status !== self::STATUS_ERROR) {
            return false;
        }

        $submissionId = $this->getSubmissionId();
        if (!$submissionId) {
            return false;
        }

        if ($siblingEvents === null) {
            return true;
        }

        foreach ($siblingEvents as $event) {
            if ($event->id <= $this->id) {
                continue;
            }

            if ($event->status !== self::STATUS_SUCCESS) {
                continue;
            }

            if ($event->getSubmissionId() === $submissionId) {
                return false;
            }
        }

        return true;
    }
}
