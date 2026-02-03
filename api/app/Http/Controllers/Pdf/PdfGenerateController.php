<?php

namespace App\Http\Controllers\Pdf;

use App\Exceptions\PdfNotSupportedException;
use App\Http\Controllers\Controller;
use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\PdfTemplate;
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
        $this->middleware('auth')->only(['getTemplateSignedUrl', 'preview']);
    }

    /**
     * Get a signed URL for PDF download by template (primary endpoint).
     * Returns a URL that can be used without auth (includes signature).
     */
    public function getTemplateSignedUrl(
        Request $request,
        Form $form,
        PdfTemplate $pdfTemplate,
        string $submission_id
    ) {
        $this->authorize('view', $form);

        // Validate template belongs to form
        if ($pdfTemplate->form_id !== $form->id) {
            abort(404, 'Template not found.');
        }

        // Resolve submission
        $submission = SubmissionUrlService::resolveSubmission($form, $submission_id);
        if (!$submission) {
            abort(404, 'Submission not found.');
        }

        $url = self::generateTemplateSignedUrl($form, $pdfTemplate, $submission);

        return response()->json(['url' => $url]);
    }

    /**
     * Generate and download PDF for a submission by template (signed URL).
     */
    public function downloadByTemplate(
        Request $request,
        Form $form,
        PdfTemplate $pdfTemplate,
        string $submission_id
    ) {
        // Validate template belongs to form
        if ($pdfTemplate->form_id !== $form->id) {
            abort(404, 'Template not found.');
        }

        // Resolve submission
        $submission = SubmissionUrlService::resolveSubmission($form, $submission_id);
        if (!$submission) {
            abort(404, 'Submission not found.');
        }

        return $this->servePdfFromTemplate($form, $submission, $pdfTemplate);
    }

    /**
     * Preview PDF using latest submission or empty data (admin only).
     */
    public function preview(
        Request $request,
        Form $form,
        PdfTemplate $pdfTemplate
    ) {
        $this->authorize('update', $form);

        // Validate template belongs to form
        if ($pdfTemplate->form_id !== $form->id) {
            abort(404, 'Template not found.');
        }

        // Get latest submission or create a fake one with empty data for preview
        $submission = $form->submissions()->latest()->first();
        if (!$submission) {
            $submission = new FormSubmission([
                'form_id' => $form->id,
                'data' => [],
            ]);
            $submission->id = 0; // Fake ID for preview
        }

        return $this->servePdfFromTemplate($form, $submission, $pdfTemplate);
    }

    /**
     * Serve PDF from template.
     */
    private function servePdfFromTemplate(
        Form $form,
        FormSubmission $submission,
        PdfTemplate $template
    ) {
        // Validate submission belongs to form
        if ($submission->form_id !== $form->id && $submission->id !== 0) {
            abort(404, 'Submission not found.');
        }

        // Get or generate the PDF
        try {
            $pdfPath = $this->cache->getOrGenerateFromTemplate($form, $submission, $template, $this->generator);
        } catch (PdfNotSupportedException $e) {
            abort(422, $e->getMessage());
        }

        if (!Storage::exists($pdfPath)) {
            abort(500, 'Failed to generate PDF.');
        }

        // Get filename from template
        $filenamePattern = $template->filename_pattern ?? '{form_name}-{submission_id}.pdf';
        $filename = $this->generateFilename($filenamePattern, $form, $submission);

        return Storage::download($pdfPath, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Generate a signed URL for template-based PDF download.
     */
    public static function generateTemplateSignedUrl(
        Form $form,
        PdfTemplate $template,
        FormSubmission $submission
    ): string {
        $submissionId = SubmissionUrlService::getSubmissionIdentifier($submission);

        return URL::temporarySignedRoute(
            'open.forms.pdf-templates.download-submission',
            now()->addHours(24),
            [
                'form' => $form->id,
                'pdfTemplate' => $template->id,
                'submission_id' => $submissionId,
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
            '{submission_id}' => $submission->id ?: 'preview',
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
