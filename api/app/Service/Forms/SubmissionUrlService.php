<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\PdfTemplate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SubmissionUrlService
{
    /**
     * Get the public submission identifier for a submission.
     *
     * @param FormSubmission $submission
     * @return string
     */
    public static function getSubmissionIdentifier(FormSubmission $submission): string
    {
        if (!$submission->public_id) {
            $publicId = Str::uuid()->toString();
            $updated = FormSubmission::query()
                ->whereKey($submission->id)
                ->whereNull('public_id')
                ->update(['public_id' => $publicId]);

            $submission->public_id = $updated ? $publicId : $submission->refresh()->public_id;
        }

        return $submission->public_id;
    }

    /**
     * Get the submission identifier by submission ID.
     *
     * @param Form $form
     * @param int $submissionId
     * @return string
     */
    public static function getSubmissionIdentifierById(Form $form, int $submissionId): string
    {
        $submission = $form->submissions()->find($submissionId);

        if (!$submission) {
            abort(404, 'Submission not found');
        }

        return self::getSubmissionIdentifier($submission);
    }

    /**
     * Resolve a submission from its public UUID identifier.
     *
     * @param Form $form
     * @param string $identifier UUID
     * @return FormSubmission|null
     */
    public static function resolveSubmission(Form $form, string $identifier): ?FormSubmission
    {
        if (!Str::isUuid($identifier)) {
            return null;
        }

        return $form->submissions()
            ->where('public_id', $identifier)
            ->first();
    }

    /**
     * Issue a bounded download capability for one submission and template.
     */
    public static function buildPdfDownloadUrl(Form $form, PdfTemplate $template, FormSubmission $submission): string
    {
        abort_unless($template->form_id === $form->id && $submission->form_id === $form->id, 404);

        return URL::temporaryPublicSignedRoute(
            'open.forms.pdf-templates.download-submission',
            now()->addHours(24),
            [
                'form' => $form->id,
                'pdfTemplate' => $template->id,
                'submission_id' => self::getSubmissionIdentifier($submission),
            ]
        );
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
