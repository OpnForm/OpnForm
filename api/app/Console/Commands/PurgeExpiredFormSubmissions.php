<?php

namespace App\Console\Commands;

use App\Jobs\Form\PurgeExpiredFormSubmissions as PurgeExpiredFormSubmissionsJob;
use App\Models\Forms\Form;
use App\Service\Forms\SubmissionRetentionService;
use Illuminate\Console\Command;
use Throwable;

class PurgeExpiredFormSubmissions extends Command
{
    protected $signature = 'forms:purge-expired-submissions
        {--form= : Only process a specific form ID}
        {--dry-run : Count expired submissions without deleting them}';

    protected $description = 'Permanently delete form submissions past their configured retention period';

    public function handle(SubmissionRetentionService $retentionService): int
    {
        $query = Form::withTrashed()
            ->whereNotNull('submission_retention_value')
            ->whereNotNull('submission_retention_unit');

        if ($formId = $this->option('form')) {
            $query->whereKey($formId);
        }

        $dryRun = (bool) $this->option('dry-run');
        $processedForms = 0;
        $expiredSubmissions = 0;
        $failedForms = 0;

        $query->lazyById(100)->each(function (Form $form) use (
            $retentionService,
            $dryRun,
            &$processedForms,
            &$expiredSubmissions,
            &$failedForms
        ) {
            $processedForms++;

            try {
                if (!$dryRun) {
                    PurgeExpiredFormSubmissionsJob::dispatch($form->id);
                    $this->line("Form {$form->id}: purge job dispatched.");

                    return;
                }

                $count = $retentionService->countExpired($form);

                $expiredSubmissions += $count;

                if ($count > 0) {
                    $this->line("Form {$form->id}: would delete {$count} expired submission(s).");
                }
            } catch (Throwable $exception) {
                $failedForms++;
                report($exception);
                $this->error("Form {$form->id}: purge failed; remaining forms will still be processed.");
            }
        });

        $summary = $dryRun
            ? "{$expiredSubmissions} submission(s) would be deleted"
            : 'purge jobs dispatched';
        $this->info("Processed {$processedForms} form(s); {$summary}; {$failedForms} form(s) failed.");

        return $failedForms === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
