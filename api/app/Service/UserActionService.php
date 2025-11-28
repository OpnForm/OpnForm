<?php

namespace App\Service;

use App\Mail\UserBlockedEmail;
use App\Mail\UserUnblockedEmail;
use App\Models\Forms\Form;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserActionService
{
    public function block(User $user, string $reason, ?int $moderatorId): User
    {
        $user->blockUser($reason, $moderatorId);

        // Store current visibility status in tags before setting to draft
        $user->forms()->get()->each(function ($form) {
            if ($form->visibility !== 'draft') {
                $this->storePreviousFormStatus($form);
                $form->update(['visibility' => 'draft']);
            }
        });

        Log::channel('slack_admin')->info('User blocked 🚫', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'reason' => $reason,
            'moderator_id' => $moderatorId,
            'forms_count' => $user->forms()->count(),
            'actions' => [
                'Admin Panel' => front_url('/admin?user_id=' . $user->id),
            ]
        ]);

        Mail::to($user)->send(new UserBlockedEmail($user, $reason));

        return $user->fresh();
    }

    public function unblock(User $user, string $reason, int $moderatorId): User
    {
        $user->unblockUser($reason, $moderatorId);

        // Restore form visibility from previous-status tags
        $restoredFormsCount = 0;
        $user->forms()->get()->each(function ($form) use (&$restoredFormsCount) {
            $wasModified = $this->restorePreviousFormStatus($form);
            // Save the form if it was modified (either restored or cleaned up)
            // Use saveQuietly() to avoid triggering spam checks - forms are already admin-approved
            if ($wasModified || $form->isDirty()) {
                $form->saveQuietly();
                if ($wasModified) {
                    $restoredFormsCount++;
                }
            }
        });

        Log::channel('slack_admin')->info('User unblocked 🔓', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'reason' => $reason,
            'moderator_id' => $moderatorId,
            'forms_count' => $user->forms()->count(),
            'restored_forms' => $restoredFormsCount,
            'actions' => [
                'Admin Panel' => front_url('/admin?user_id=' . $user->id),
            ]
        ]);

        Mail::to($user)->send(new UserUnblockedEmail($user, $reason));

        return $user->fresh();
    }

    /**
     * Store the current form visibility status in tags before changing to draft
     */
    private function storePreviousFormStatus(Form $form): void
    {
        if ($form->visibility === 'draft') {
            return; // Don't store draft as previous status
        }

        $currentTags = $form->tags ?? [];

        // Remove any existing previous-status tags to avoid duplicates
        $currentTags = array_filter($currentTags, function ($tag) {
            return !str_starts_with($tag, 'previous-status-');
        });

        // Add the current status as a tag
        $currentTags[] = "previous-status-{$form->visibility}";

        $form->tags = $currentTags;
    }

    /**
     * Restore form visibility from previous-status tags
     * Returns true if form was successfully restored, false otherwise
     * Always cleans up invalid previous-status tags regardless of return value
     */
    private function restorePreviousFormStatus(Form $form): bool
    {
        $currentTags = $form->tags ?? [];

        // Find the previous-status tag
        $previousStatusTag = collect($currentTags)
            ->first(function ($tag) {
                return str_starts_with($tag, 'previous-status-');
            });

        if (!$previousStatusTag) {
            return false; // No previous status found
        }

        // Extract the previous status from the tag
        $previousStatus = str_replace('previous-status-', '', $previousStatusTag);

        // Remove the previous-status tag regardless of validity
        $updatedTags = array_filter($currentTags, function ($tag) {
            return !str_starts_with($tag, 'previous-status-');
        });

        // Validate the previous status is still valid
        if (!in_array($previousStatus, Form::VISIBILITY)) {
            // Still update tags to remove the invalid tag
            $form->tags = array_values($updatedTags);
            return false;
        }

        // Restore the form to its previous visibility
        $form->visibility = $previousStatus;
        $form->tags = array_values($updatedTags); // Re-index the array

        return true;
    }
}
