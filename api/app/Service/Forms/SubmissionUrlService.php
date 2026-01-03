<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use Vinkla\Hashids\Facades\Hashids;

class SubmissionUrlService
{
    /**
     * Get the submission identifier (UUID or Hashid) for a submission.
     * Returns UUID if available, otherwise falls back to Hashid.
     *
     * @param FormSubmission $submission
     * @return string
     */
    public static function getSubmissionIdentifier(FormSubmission $submission): string
    {
        return $submission->public_id ?? Hashids::encode($submission->id);
    }

    /**
     * Get the submission identifier by submission ID.
     * Fetches the submission and returns UUID if available, otherwise Hashid.
     *
     * @param Form $form
     * @param int $submissionId
     * @return string
     */
    public static function getSubmissionIdentifierById(Form $form, int $submissionId): string
    {
        $submission = $form->submissions()->find($submissionId);

        if (!$submission) {
            // Fallback to Hashid if submission not found (shouldn't happen in normal flow)
            return Hashids::encode($submissionId);
        }

        return self::getSubmissionIdentifier($submission);
    }

    /**
     * Build the edit URL for a submission.
     *
     * @param Form $form
     * @param FormSubmission|int $submission Submission instance or submission ID
     * @return string
     */
    public static function buildEditUrl(Form $form, FormSubmission|int $submission): string
    {
        $identifier = $submission instanceof FormSubmission
            ? self::getSubmissionIdentifier($submission)
            : self::getSubmissionIdentifierById($form, $submission);

        return $form->share_url . '?submission_id=' . $identifier;
    }
}
