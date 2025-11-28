<?php

namespace App\Policies;

use App\Models\Forms\Form;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\HandlesAuthorization;

class FormPolicy
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
            return $token->can('forms-read');
        }

        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return mixed
     */
    public function view(User $user, Form $form)
    {
        // Check if authenticated via Sanctum token
        if ($token = $user->currentAccessToken()) {
            $canAccess = $token->can('forms-read') || $token->can('manage-integrations');
            return $canAccess && $user->ownsForm($form);
        }

        // Fallback to JWT / session logic
        return $user->ownsForm($form);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return mixed
     */
    public function create(User $user, Workspace $workspace)
    {
        $userIsNotReadonly = !$workspace->isReadonlyUser($user);
        // If using Sanctum token, ensure the token has write ability
        if ($token = $user->currentAccessToken()) {
            return $token->can('forms-write') && $userIsNotReadonly;
        }

        return $userIsNotReadonly;
    }

    /**
     * Determine whether the user can perform write operations on the model.
     */
    private function canPerformWriteOperation(User $user, Form $form): bool
    {
        $ownsAndWritable = $user->ownsForm($form) && !$form->workspace->isReadonlyUser($user);

        // If using Sanctum token, ensure the token has write ability
        if ($token = $user->currentAccessToken()) {
            return $token->can('forms-write') && $ownsAndWritable;
        }

        return $ownsAndWritable;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return mixed
     */
    public function update(User $user, Form $form)
    {
        return $this->canPerformWriteOperation($user, $form);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return mixed
     */
    public function delete(User $user, Form $form)
    {
        return $this->canPerformWriteOperation($user, $form);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return mixed
     */
    public function restore(User $user, Form $form)
    {
        return $this->canPerformWriteOperation($user, $form);
    }

    /**
     * Determine whether the user can manage integrations on the model.
     * For Sanctum tokens, requires the manage-integrations ability.
     * For other auth methods, allows if user has write permission.
     *
     * @return mixed
     */
    public function manageIntegrations(User $user, Form $form)
    {
        // First check if user has write permission
        if (!$this->canPerformWriteOperation($user, $form)) {
            return false;
        }

        // If using Sanctum token, ensure it has manage-integrations ability
        if ($token = $user->currentAccessToken()) {
            return $token->can('manage-integrations');
        }

        // For non-Sanctum auth (JWT/session), allow if they can write
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return mixed
     */
    public function forceDelete(User $user, Form $form)
    {
        return $this->canPerformWriteOperation($user, $form);
    }

    /**
     * Determine whether a user can answer/submit to the form.
     * This method checks if the form is open for public submissions.
     *
     * @param  \App\Models\User|null  $user
     * @param  \App\Models\Forms\Form  $form
     * @return bool
     */
    public function answer(?User $user, Form $form)
    {
        return !$form->is_closed
            && !$form->max_number_of_submissions_reached
            && $form->visibility === 'public';
    }
}
