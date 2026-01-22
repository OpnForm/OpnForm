<?php

use App\Models\Integration\FormIntegration;
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

describe('PDF Generation - Signed URL', function () {
    it('can get a signed url for pdf download', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Create template
        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        // Create PDF integration
        $integration = FormIntegration::create([
            'form_id' => $form->id,
            'integration_id' => 'pdf',
            'status' => 'active',
            'data' => [
                'template_id' => $template->id,
                'zone_mappings' => [],
                'filename_pattern' => '{form_name}-{submission_id}.pdf',
            ],
        ]);

        // Create submission
        $submission = $form->submissions()->create([
            'data' => ['name' => 'Test User'],
        ]);

        $response = $this->getJson(
            route('open.forms.submissions.pdf.signed-url', [
                'form' => $form,
                'submission_id' => getEncodedSubmissionId($submission),
                'integration' => $integration->id,
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

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => 'pdf-templates/1/template.pdf',
            'file_size' => 1000,
            'page_count' => 1,
        ]);

        $integration = FormIntegration::create([
            'form_id' => $form->id,
            'integration_id' => 'pdf',
            'status' => 'active',
            'data' => [
                'template_id' => $template->id,
                'zone_mappings' => [],
            ],
        ]);

        $submission = $form->submissions()->create([
            'data' => [],
        ]);

        $response = $this->getJson(
            route('open.forms.submissions.pdf.signed-url', [
                'form' => $form,
                'submission_id' => getEncodedSubmissionId($submission),
                'integration' => $integration->id,
            ])
        );

        $response->assertStatus(401);
    });

    it('returns 404 for non-existent submission', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => 'pdf-templates/1/template.pdf',
            'file_size' => 1000,
            'page_count' => 1,
        ]);

        $integration = FormIntegration::create([
            'form_id' => $form->id,
            'integration_id' => 'pdf',
            'status' => 'active',
            'data' => [
                'template_id' => $template->id,
                'zone_mappings' => [],
            ],
        ]);

        $response = $this->getJson(
            route('open.forms.submissions.pdf.signed-url', [
                'form' => $form,
                'submission_id' => 99999,
                'integration' => $integration->id,
            ])
        );

        $response->assertStatus(404);
    });

    it('returns 400 for non-pdf integration', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Create email integration instead of PDF
        $integration = FormIntegration::create([
            'form_id' => $form->id,
            'integration_id' => 'email',
            'status' => 'active',
            'data' => [
                'send_to' => $user->email,
            ],
        ]);

        $submission = $form->submissions()->create([
            'data' => [],
        ]);

        $response = $this->getJson(
            route('open.forms.submissions.pdf.signed-url', [
                'form' => $form,
                'submission_id' => getEncodedSubmissionId($submission),
                'integration' => $integration->id,
            ])
        );

        $response->assertStatus(400);
    });
});

describe('PDF Generation - Serve PDF', function () {
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
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $integration = FormIntegration::create([
            'form_id' => $form->id,
            'integration_id' => 'pdf',
            'status' => 'active',
            'data' => [
                'template_id' => $template->id,
                'zone_mappings' => [],
                'filename_pattern' => '{form_name}-{submission_id}.pdf',
            ],
        ]);

        $submission = $form->submissions()->create([
            'data' => ['name' => 'John Doe'],
        ]);

        // Generate signed URL
        $signedUrl = URL::temporarySignedRoute(
            'forms.submissions.pdf.signed',
            now()->addHours(1),
            [
                'form' => $form->id,
                'submission_id' => getEncodedSubmissionId($submission),
                'integration' => $integration->id,
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

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => 'pdf-templates/1/template.pdf',
            'file_size' => 1000,
            'page_count' => 1,
        ]);

        $integration = FormIntegration::create([
            'form_id' => $form->id,
            'integration_id' => 'pdf',
            'status' => 'active',
            'data' => [
                'template_id' => $template->id,
                'zone_mappings' => [],
            ],
        ]);

        $submission = $form->submissions()->create([
            'data' => [],
        ]);

        // Request without signature
        $response = $this->get(
            route('forms.submissions.pdf.signed', [
                'form' => $form,
                'submission_id' => getEncodedSubmissionId($submission),
                'integration' => $integration->id,
            ])
        );

        $response->assertStatus(403);
    });

    it('rejects inactive pdf integration', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $integration = FormIntegration::create([
            'form_id' => $form->id,
            'integration_id' => 'pdf',
            'status' => 'inactive', // Inactive!
            'data' => [
                'template_id' => $template->id,
                'zone_mappings' => [],
            ],
        ]);

        $submission = $form->submissions()->create([
            'data' => [],
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'forms.submissions.pdf.signed',
            now()->addHours(1),
            [
                'form' => $form->id,
                'submission_id' => getEncodedSubmissionId($submission),
                'integration' => $integration->id,
            ]
        );

        $response = $this->get($signedUrl);

        $response->assertStatus(400);
    });
});

describe('PDF Integration Creation', function () {
    it('can create a pdf integration', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $pdfContent = createValidPdfForGeneration();
        $templatePath = "pdf-templates/{$form->id}/template.pdf";
        Storage::put($templatePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $response = $this->postJson(route('open.forms.integrations.create', $form), [
            'integration_id' => 'pdf',
            'status' => 'active',
            'data' => [
                'template_id' => $template->id,
                'zone_mappings' => [
                    [
                        'id' => 'zone_1',
                        'page' => 1,
                        'x' => 10,
                        'y' => 10,
                        'width' => 50,
                        'height' => 10,
                        'field_id' => 'name',
                        'font_size' => 12,
                        'font_color' => '#000000',
                    ],
                ],
                'filename_pattern' => '{form_name}-{submission_id}.pdf',
            ],
        ]);

        $response->assertSuccessful();

        $integration = FormIntegration::where('form_id', $form->id)
            ->where('integration_id', 'pdf')
            ->first();

        expect($integration)->not->toBeNull();
        expect($integration->data->template_id)->toBe($template->id);
    });

    it('validates template_id is required', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        $response = $this->postJson(route('open.forms.integrations.create', $form), [
            'integration_id' => 'pdf',
            'status' => 'active',
            'data' => [
                'zone_mappings' => [],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data.template_id']);
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

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        // Get the first text field from the form
        $textField = collect($form->properties)->firstWhere('type', 'text');

        $integration = FormIntegration::create([
            'form_id' => $form->id,
            'integration_id' => 'pdf',
            'status' => 'active',
            'data' => [
                'template_id' => $template->id,
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
            ],
        ]);

        $submission = $form->submissions()->create([
            'data' => [$textField['id'] => 'John Doe'],
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'forms.submissions.pdf.signed',
            now()->addHours(1),
            [
                'form' => $form->id,
                'submission_id' => getEncodedSubmissionId($submission),
                'integration' => $integration->id,
            ]
        );

        $response = $this->get($signedUrl);

        $response->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');

        // Check that generated PDF exists in cache
        expect(Storage::allFiles('pdf-generated'))->not->toBeEmpty();
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
