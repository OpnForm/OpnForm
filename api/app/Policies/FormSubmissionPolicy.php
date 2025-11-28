<?php

namespace App\Policies;

use App\Models\Forms\FormSubmission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FormSubmissionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can update the form submission.
     */
    public function update(User $user, FormSubmission $submission)
    {
        $form = $submission->form;

        $ownsAndWritable = $user->ownsForm($form) && !$form->workspace->isReadonlyUser($user);

        if ($token = $user->currentAccessToken()) {
            return $token->can('forms-write') && $ownsAndWritable;
        }

        return $ownsAndWritable;
    }
}
