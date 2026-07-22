<?php

namespace App\Console\Commands;

use App\Jobs\Form\DeletePendingSubmissionFile;
use App\Models\Forms\FormSubmissionFileDeletion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RetryPendingSubmissionFileDeletions extends Command
{
    protected $signature = 'forms:retry-pending-submission-file-deletions {--limit=1000}';

    protected $description = 'Redispatch pending form submission file deletions';

    public function handle(): int
    {
        $limit = max(1, min((int) $this->option('limit'), 10000));
        $failed = 0;

        $deletionIds = DB::transaction(function () use ($limit) {
            $ids = FormSubmissionFileDeletion::query()
                ->where('next_attempt_at', '<=', now())
                ->oldest('next_attempt_at')
                ->oldest('id')
                ->lockForUpdate()
                ->limit($limit)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                FormSubmissionFileDeletion::query()
                    ->whereKey($ids)
                    ->update(['next_attempt_at' => now()->addMinutes(15)]);
            }

            return $ids;
        });

        $deletionIds
            ->each(function (int $deletionId) use (&$failed) {
                try {
                    DeletePendingSubmissionFile::dispatch($deletionId);
                } catch (Throwable $exception) {
                    $failed++;
                    report($exception);

                    try {
                        FormSubmissionFileDeletion::query()
                            ->whereKey($deletionId)
                            ->update(['next_attempt_at' => now()]);
                    } catch (Throwable $resetException) {
                        report($resetException);
                    }
                }
            });

        $claimed = $deletionIds->count();
        $this->info("Claimed {$claimed} pending file deletion(s); {$failed} dispatch attempt(s) failed.");

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
