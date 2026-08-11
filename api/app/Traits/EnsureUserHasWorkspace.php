<?php

namespace App\Traits;

use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;

trait EnsureUserHasWorkspace
{
    /**
     * Ensure a user has at least one workspace.
     * If they don't, create a new workspace for them.
     */
    protected function ensureUserHasWorkspace(User $user): void
    {
        if ($user->workspaces()->count() === 0) {
            Log::info('Creating new workspace for user with no workspaces', [
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]);

            $newWorkspace = Workspace::create([
                'name' => 'My Workspace',
            ]);

            UserWorkspace::create([
                'workspace_id' => $newWorkspace->id,
                'user_id' => $user->id,
                'role' => User::ROLE_ADMIN,
            ]);
        }
    }
}
