<?php

namespace App\Jobs\Billing;

use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Seat billing is no longer synchronized by the application runtime.
 * Workspace membership changes only affect access control and cache state.
 */
class WorkspaceUsersUpdated implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Workspace $workspace)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        return;
    }
}
