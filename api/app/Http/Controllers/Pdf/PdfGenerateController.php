<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\Integration\FormIntegration;
use App\Service\Pdf\PdfCacheService;
use App\Service\Pdf\PdfGeneratorService;
use App\Service\Forms\SubmissionUrlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PdfGenerateController extends Controller
{
    public function __construct(
        private PdfGeneratorService $generator,
        private PdfCacheService $cache
    ) {
        $this->middleware('auth')->only(['generateAuthenticated', 'getSignedUrl']);
    }

    /**
     * Get a signed URL for PDF download (authenticated endpoint).
     * Returns a URL that can be used without auth (includes signature).
     * Used by both results table and success page.
     */
    public function getSignedUrl(
        Request $request,
        Form $form,
        string $submission_id,
        FormIntegration $integration
    ) {
        // Check user has access to the form
        $this->authorize('view', $form);

        // Resolve submission (handles UUID and Hashid formats)
        $submission = SubmissionUrlService::resolveSubmission($form, $submission_id);
        if (!$submission) {
            abort(404, 'Submission not found.');
        }

        // Validate relationships
        if ($integration->form_id !== $form->id) {
            abort(404, 'Integration not found.');
        }
        if ($integration->integration_id !== 'pdf') {
            abort(400, 'Invalid integration type.');
        }

        $url = self::generateSignedUrl($form, $submission, $integration);

        return response()->json(['url' => $url]);
    }

    /**
     * Generate and download PDF for a submission (signed URL).
     */
    public function generateSigned(
        Request $request,
        Form $form,
        string $submission_id,
        FormIntegration $integration
    ) {
        // Resolve submission (handles UUID and Hashid formats)
        $submission = SubmissionUrlService::resolveSubmission($form, $submission_id);
        if (!$submission) {
            abort(404, 'Submission not found.');
        }

        // Signed URL validates access
        return $this->generatePdf($form, $submission, $integration);
    }

    /**
     * Common PDF generation logic.
     */
    private function generatePdf(
        Form $form,
        FormSubmission $submission,
        FormIntegration $integration
    ) {
        // Validate that the integration belongs to the form
        if ($integration->form_id !== $form->id) {
            abort(404, 'Integration not found.');
        }

        // Validate that the submission belongs to the form
        if ($submission->form_id !== $form->id) {
            abort(404, 'Submission not found.');
        }

        // Validate that this is a PDF integration
        if ($integration->integration_id !== 'pdf') {
            abort(400, 'Invalid integration type.');
        }

        // Check if integration is active
        if ($integration->status !== 'active') {
            abort(400, 'PDF integration is not active.');
        }

        // Get or generate the PDF
        $pdfPath = $this->cache->getOrGenerate($form, $submission, $integration, $this->generator);

        if (!Storage::exists($pdfPath)) {
            abort(500, 'Failed to generate PDF.');
        }

        // Get filename from integration data
        $data = $integration->data;
        $filenamePattern = $data->filename_pattern ?? '{form_name}-{submission_id}.pdf';
        $filename = $this->generateFilename($filenamePattern, $form, $submission);

        // Stream the PDF to the browser
        return Storage::download($pdfPath, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Generate a signed URL for PDF download (for success page).
     */
    public static function generateSignedUrl(
        Form $form,
        FormSubmission $submission,
        FormIntegration $integration
    ): string {
        // Use encoded submission ID (UUID or Hashid) for public access
        $submissionId = SubmissionUrlService::getSubmissionIdentifier($submission);

        return URL::temporarySignedRoute(
            'forms.submissions.pdf.signed',
            now()->addHours(24),
            [
                'form' => $form->id,
                'submission_id' => $submissionId,
                'integration' => $integration->id,
            ]
        );
    }

    /**
     * Generate filename from pattern.
     */
    private function generateFilename(string $pattern, Form $form, FormSubmission $submission): string
    {
        $replacements = [
            '{form_name}' => Str::slug($form->title),
            '{submission_id}' => $submission->id,
            '{date}' => now()->format('Y-m-d'),
            '{timestamp}' => now()->timestamp,
        ];

        $filename = str_replace(array_keys($replacements), array_values($replacements), $pattern);

        // Ensure .pdf extension
        if (!str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        // Sanitize filename
        return preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename);
    }
}
