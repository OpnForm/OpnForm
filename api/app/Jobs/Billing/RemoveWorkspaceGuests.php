<?php

namespace App\Jobs\Billing;

use App\Models\User;
use App\Models\Workspace;
use App\Traits\EnsureUserHasWorkspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RemoveWorkspaceGuests implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use EnsureUserHasWorkspace;

    /**
     * Create a new job instance.
     */
    public function __construct(public User $user)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // If pricing not enabled
        if (!pricing_enabled()) {
            return;
        }

        if ($this->user->is_subscribed) {
            return;
        }

        // User is not subscribed anymore - remove guests
        $this->user->workspaces->each(function (Workspace $workspace) {
            // Flush workspace cache to be sure we have the latest data
            $workspace->flush();
            if ($workspace->is_pro) {
                // Another user still has pro subscription
                return;
            }

            // Detach all users from the workspace (except the owner)
            foreach ($workspace->users()->where('users.id', '!=', $this->user->id)->get() as $user) {
                Log::info('Detaching user from workspace', [
                    'workspace_id' => $workspace->id,
                    'workspace_name' => $workspace->name,
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);
                $workspace->users()->detach($user);
                $this->ensureUserHasWorkspace($user);
            }
        });
    }
}
