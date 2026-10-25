<?php

namespace App\Console\Commands;

use App\Models\Forms\SubmissionOperation;
use App\Service\Forms\SubmissionOperationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchSubmissionOutbox extends Command
{
    protected $signature = 'forms:dispatch-submission-outbox {--limit=100}';

    protected $description = 'Dispatch committed pending submission events; quarantine stale unknown outcomes';

    public function handle(SubmissionOperationService $service): int
    {
        $limit = max(1, min((int) $this->option('limit'), 1000));
        SubmissionOperation::where('outbox_state', 'dispatching')->where('updated_at', '<', now()->subMinutes(5))
            ->update(['outbox_state' => 'ambiguous', 'updated_at' => now()]);
        DB::table('submission_effects')->where('state', 'invoking')->where('updated_at', '<', now()->subSeconds(3700))
            ->update(['state' => 'ambiguous', 'updated_at' => now()]);
        SubmissionOperation::whereNotNull('completed_at')->where('outbox_state', 'pending')->oldest('id')->limit($limit)
            ->pluck('id')->each(fn ($id) => $service->dispatchOutbox($id));
        SubmissionOperation::whereNotNull('completed_at')->whereJsonLength('temporary_paths', '>', 0)->oldest('updated_at')->limit($limit)
            ->pluck('id')->each(fn ($id) => $service->cleanupTemporaryFiles($id));
        $ambiguous = SubmissionOperation::where('outbox_state', 'ambiguous')->count()
            + DB::table('submission_effects')->where('state', 'ambiguous')->count();
        $this->info("Outbox pass complete; {$ambiguous} ambiguous outcome(s) require reconciliation; no automatic replay.");

        return self::SUCCESS;
    }
}
