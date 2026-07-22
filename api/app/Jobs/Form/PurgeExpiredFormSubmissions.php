<?php

namespace App\Jobs\Form;

use App\Models\Forms\Form;
use App\Service\Forms\SubmissionRetentionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PurgeExpiredFormSubmissions implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;

    public int $uniqueFor = 3600;

    public function __construct(public int $formId)
    {
    }

    public function handle(SubmissionRetentionService $retentionService): void
    {
        $form = Form::withTrashed()->find($this->formId);

        if (!$form || !$form->submission_retention_value || !$form->submission_retention_unit) {
            return;
        }

        $deleted = $retentionService->purge($form);

        if ($deleted > 0) {
            Log::info('Expired form submissions purged', [
                'form_id' => $form->id,
                'deleted_submissions' => $deleted,
            ]);
        }
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("submission-retention:{$this->formId}"))
                ->releaseAfter(300)
                ->expireAfter(3900),
        ];
    }

    public function uniqueId(): string
    {
        return (string) $this->formId;
    }

    public function backoff(): array
    {
        return [300, 900];
    }
}
