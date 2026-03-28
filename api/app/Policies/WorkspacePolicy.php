<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Models\UserWorkspace;
use App\Service\UserHelper;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class WorkspacePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @return mixed
     */
    public function viewAny(User $user)
    {
        // Check if authenticated via Sanctum token
        if ($token = $user->currentAccessToken()) {
            return $token->can('workspaces-read');
        }

        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return mixed
     */
    public function view(User $user, Workspace $workspace)
    {
        if ($token = $user->currentAccessToken()) {
            $canAccess = $token->can('workspaces-read');

            return $canAccess && $user->ownsWorkspace($workspace);
        }

        return $user->ownsWorkspace($workspace);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return mixed
     */
    public function create(User $user)
    {
        // Check if user already has a workspace
        if (!$user->is_subscribed && $user->workspaces()->count() > 0) {
            return Response::deny('You have reached the limit for free workspaces. Upgrade to Pro to create additional workspaces.');
        }

        if ($token = $user->currentAccessToken()) {
            return $token->can('workspaces-write');
        }

        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return mixed
     */
    public function update(User $user, Workspace $workspace)
    {
        if ($token = $user->currentAccessToken()) {
            return $token->can('workspaces-write') && $user->ownsWorkspace($workspace);
        }

        return $user->ownsWorkspace($workspace);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return mixed
     */
    public function delete(User $user, Workspace $workspace)
    {
        if (!$user->ownsWorkspace($workspace)) {
            return Response::deny('You cannot delete this workspace.');
        }

        if ($user->workspaces()->count() <= 1) {
            return Response::deny('You cannot delete your last workspace. Delete your account instead.');
        }

        if ($token = $user->currentAccessToken()) {
            return $token->can('workspaces-write');
        }

        return true;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return mixed
     */
    public function restore(User $user, Workspace $workspace)
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return mixed
     */
    public function forceDelete(User $user, Workspace $workspace)
    {
        return false;
    }

    public function inviteUser(User $user, Workspace $workspace)
    {
        if (!$this->adminAction($user, $workspace)) {
            return Response::deny('You need to be an admin of this workspace to do this.');
        }

        // If self-hosted, allow
        if (!pricing_enabled()) {
            return Response::allow();
        }

        if (!$workspace->is_pro) {
            return Response::deny('You need a Pro subscription to invite a user.');
        }

        // In case of special license, check license limit
        $billingOwner = $workspace->billingOwners()->first();
        if ($license = $billingOwner->activeLicense()) {
            $userActiveMembers = (new UserHelper($billingOwner))->getActiveMembersCount();
            if ($userActiveMembers >= $license->max_users_limit_count) {
                return Response::deny('You have reached the maximum number of users allowed with your license.');
            }
        }

        // If using Sanctum token, require write ability first
        if ($token = $user->currentAccessToken()) {
            if (! $token->can('workspaces-write')) {
                return Response::deny('Token lacks workspaces:write ability.');
            }
        }

        return true;
    }

    /**
     * Determine whether the user is an admin in the workspace.
     *
     * @return mixed
     */
    public function adminAction(User $user, Workspace $workspace)
    {
        // Sanctum token must include write ability
        if ($token = $user->currentAccessToken()) {
            if (! $token->can('workspaces-write')) {
                return false;
            }
        }

        $userWorkspace = UserWorkspace::where('user_id', $user->id)
            ->where('workspace_id', $workspace->id)
            ->first();
        return $userWorkspace && $userWorkspace->role === 'admin';
    }

    public function ownsWorkspace(User $user, Workspace $workspace)
    {
        return $user->ownsWorkspace($workspace);
    }
}
