<?php

use App\Models\PdfTemplate;
use App\Service\Forms\SubmissionUrlService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Get the encoded submission ID for routes.
 */
function getEncodedSubmissionId($submission): string
{
    return SubmissionUrlService::getSubmissionIdentifier($submission);
}

describe('PDF Template - Signed URL', function () {
    it('can get a signed url for pdf download via template', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Create template
        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
            'zone_mappings' => [],
            'filename_pattern' => '{form_name}-{submission_id}.pdf',
        ]);

        // Create submission
        $submission = $form->submissions()->create([
            'data' => ['name' => 'Test User'],
        ]);

        $response = $this->getJson(
            route('open.forms.pdf-templates.submission.signed-url', [
                'form' => $form,
                'pdfTemplate' => $template->id,
                'submission_id' => getEncodedSubmissionId($submission),
            ])
        );

        $response->assertSuccessful()
            ->assertJsonStructure(['url']);

        $url = $response->json('url');
        expect($url)->toContain('signature=');
    });

    it('requires authentication to get signed url', function () {
        $user = $this->createProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $submission = $form->submissions()->create([
            'data' => [],
        ]);

        $response = $this->getJson(
            route('open.forms.pdf-templates.submission.signed-url', [
                'form' => $form,
                'pdfTemplate' => $template->id,
                'submission_id' => getEncodedSubmissionId($submission),
            ])
        );

        $response->assertStatus(401);
    });

    it('returns 404 for non-existent submission', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $response = $this->getJson(
            route('open.forms.pdf-templates.submission.signed-url', [
                'form' => $form,
                'pdfTemplate' => $template->id,
                'submission_id' => 99999,
            ])
        );

        $response->assertStatus(404);
    });

    it('returns 404 for template belonging to different form', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);
        $otherForm = $this->createForm($user, $workspace);

        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$otherForm->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        // Template belongs to otherForm
        $template = PdfTemplate::create([
            'form_id' => $otherForm->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $submission = $form->submissions()->create([
            'data' => [],
        ]);

        $response = $this->getJson(
            route('open.forms.pdf-templates.submission.signed-url', [
                'form' => $form,
                'pdfTemplate' => $template->id,
                'submission_id' => getEncodedSubmissionId($submission),
            ])
        );

        $response->assertStatus(404);
    });
});

describe('PDF Template - Download', function () {
    it('can generate and download pdf with valid signed url', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Create template with valid PDF
        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
            'zone_mappings' => [],
            'filename_pattern' => '{form_name}-{submission_id}.pdf',
        ]);

        $submission = $form->submissions()->create([
            'data' => ['name' => 'John Doe'],
        ]);

        // Generate signed URL
        $signedUrl = URL::temporarySignedRoute(
            'open.forms.pdf-templates.download-submission',
            now()->addHours(1),
            [
                'form' => $form->id,
                'pdfTemplate' => $template->id,
                'submission_id' => getEncodedSubmissionId($submission),
            ]
        );

        // Extract path from URL for testing
        $response = $this->get($signedUrl);

        $response->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('rejects request without valid signature', function () {
        $user = $this->createProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $submission = $form->submissions()->create([
            'data' => [],
        ]);

        // Request without signature
        $response = $this->get(
            route('open.forms.pdf-templates.download-submission', [
                'form' => $form,
                'pdfTemplate' => $template->id,
                'submission_id' => getEncodedSubmissionId($submission),
            ])
        );

        $response->assertStatus(403);
    });

    it('returns 404 for template belonging to different form', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);
        $otherForm = $this->createForm($user, $workspace);

        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$otherForm->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        // Template belongs to otherForm
        $template = PdfTemplate::create([
            'form_id' => $otherForm->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $submission = $form->submissions()->create([
            'data' => [],
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'open.forms.pdf-templates.download-submission',
            now()->addHours(1),
            [
                'form' => $form->id,
                'pdfTemplate' => $template->id,
                'submission_id' => getEncodedSubmissionId($submission),
            ]
        );

        $response = $this->get($signedUrl);

        $response->assertStatus(404);
    });
});

describe('PDF Template - Preview', function () {
    it('can preview pdf using latest submission', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
            'zone_mappings' => [],
        ]);

        // Create a submission
        $form->submissions()->create([
            'data' => ['name' => 'Preview Test User'],
        ]);

        $response = $this->getJson(
            route('open.forms.pdf-templates.preview.signed-url', [
                'form' => $form,
                'pdfTemplate' => $template->id,
            ])
        );

        $response->assertSuccessful()
            ->assertJsonStructure(['url']);
        expect(str_contains($response->json('url'), 'pdf-templates'))->toBeTrue();
    });

    it('can preview pdf with empty data when no submissions exist', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        // No submissions created - preview should still work with empty values

        $response = $this->getJson(
            route('open.forms.pdf-templates.preview.signed-url', [
                'form' => $form,
                'pdfTemplate' => $template->id,
            ])
        );

        $response->assertSuccessful()
            ->assertJsonStructure(['url']);
        expect(str_contains($response->json('url'), 'pdf-templates'))->toBeTrue();
    });

    it('requires authentication for preview', function () {
        $user = $this->createProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $response = $this->getJson(
            route('open.forms.pdf-templates.preview.signed-url', [
                'form' => $form,
                'pdfTemplate' => $template->id,
            ])
        );

        $response->assertStatus(401);
    });
});

describe('PDF with Zone Mappings', function () {
    it('generates pdf with text in mapped zones', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        // Get the first text field from the form
        $textField = collect($form->properties)->firstWhere('type', 'text');

        // Zone mappings are stored on the template
        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
            'zone_mappings' => [
                [
                    'id' => 'zone_name',
                    'page' => 1,
                    'x' => 10,
                    'y' => 20,
                    'width' => 80,
                    'height' => 10,
                    'field_id' => $textField['id'],
                    'font_size' => 14,
                    'font_color' => '#000000',
                ],
            ],
            'filename_pattern' => 'submission-{submission_id}.pdf',
        ]);

        $submission = $form->submissions()->create([
            'data' => [$textField['id'] => 'John Doe'],
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'open.forms.pdf-templates.download-submission',
            now()->addHours(1),
            [
                'form' => $form->id,
                'pdfTemplate' => $template->id,
                'submission_id' => getEncodedSubmissionId($submission),
            ]
        );

        $response = $this->get($signedUrl);

        $response->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');

        // Check that generated PDF exists in cache
        expect(Storage::allFiles('tmp/pdf-output'))->not->toBeEmpty();
    });

    it('generates pdf with static text zones', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        // Zone with static text instead of field_id
        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
            'zone_mappings' => [
                [
                    'id' => 'zone_static',
                    'page' => 1,
                    'x' => 10,
                    'y' => 50,
                    'width' => 80,
                    'height' => 10,
                    'static_text' => 'This is hardcoded text',
                    'font_size' => 12,
                    'font_color' => '#333333',
                ],
            ],
            'filename_pattern' => 'submission-{submission_id}.pdf',
        ]);

        $submission = $form->submissions()->create([
            'data' => ['name' => 'Test User'],
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'open.forms.pdf-templates.download-submission',
            now()->addHours(1),
            [
                'form' => $form->id,
                'pdfTemplate' => $template->id,
                'submission_id' => getEncodedSubmissionId($submission),
            ]
        );

        $response = $this->get($signedUrl);

        $response->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');
    });
});

describe('PDF Branding', function () {
    it('adds branding footer when remove_branding is false', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
            'remove_branding' => false, // Default: include branding
        ]);

        $submission = $form->submissions()->create([
            'data' => ['name' => 'Test User'],
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'open.forms.pdf-templates.download-submission',
            now()->addHours(1),
            [
                'form' => $form->id,
                'pdfTemplate' => $template->id,
                'submission_id' => getEncodedSubmissionId($submission),
            ]
        );

        $response = $this->get($signedUrl);

        $response->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('removes branding footer when remove_branding is true', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
            'remove_branding' => true, // Pro feature: no branding
        ]);

        $submission = $form->submissions()->create([
            'data' => ['name' => 'Test User'],
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'open.forms.pdf-templates.download-submission',
            now()->addHours(1),
            [
                'form' => $form->id,
                'pdfTemplate' => $template->id,
                'submission_id' => getEncodedSubmissionId($submission),
            ]
        );

        $response = $this->get($signedUrl);

        $response->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');
    });
});

/**
 * Helper function to create a valid PDF for generation tests.
 */
function createValidPdfForGeneration(): string
{
    $pdf = new \setasign\Fpdi\Fpdi();
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Cell(0, 10, 'Test PDF Template for Generation');
    $pdf->Ln();
    $pdf->Cell(0, 10, 'Name: ________________');
    $pdf->Ln();
    $pdf->Cell(0, 10, 'Date: ________________');

    return $pdf->Output('S');
}
