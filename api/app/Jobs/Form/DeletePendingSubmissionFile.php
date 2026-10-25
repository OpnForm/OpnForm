<?php

namespace App\Jobs\Form;

use App\Models\Forms\FormSubmissionFileDeletion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DeletePendingSubmissionFile implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public int $uniqueFor = 7200;

    public function __construct(public int $deletionId)
    {
    }

    public function handle(): void
    {
        $deletion = FormSubmissionFileDeletion::find($this->deletionId);

        if (!$deletion) {
            return;
        }

        try {
            if (Storage::exists($deletion->path) && !Storage::delete($deletion->path)) {
                throw new RuntimeException("Unable to delete submission file at {$deletion->path}.");
            }

            $deletion->delete();
        } catch (Throwable $exception) {
            $deletion->forceFill([
                'attempts' => $deletion->attempts + 1,
                'last_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("submission-file-deletion:{$this->deletionId}"))
                ->releaseAfter(60)
                ->expireAfter(300),
        ];
    }

    public function uniqueId(): string
    {
        return (string) $this->deletionId;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }
}
