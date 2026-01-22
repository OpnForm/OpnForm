<?php

use App\Exceptions\PdfNotSupportedException;
use App\Models\Forms\Form;
use App\Models\Integration\FormIntegration;
use App\Models\PdfTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Service\Pdf\PdfGeneratorService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function createTestForm(array $attributes = []): Form
{
    $user = User::factory()->create();
    $workspace = Workspace::create(['name' => 'Test Workspace', 'icon' => '📝']);
    $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

    $defaultProps = [
        ['id' => 'name', 'name' => 'Name', 'type' => 'text'],
        ['id' => 'email', 'name' => 'Email', 'type' => 'email'],
    ];

    return Form::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withProperties($attributes['properties'] ?? $defaultProps)
        ->create(array_diff_key($attributes, ['properties' => true]));
}

describe('PdfGeneratorService', function () {
    it('generates a pdf from template and submission data', function () {
        // Create valid PDF template
        $pdfContent = createTestPdf();
        $templatePath = 'pdf-templates/1/template.pdf';
        Storage::put($templatePath, $pdfContent);

        $form = createTestForm();
        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $submission = $form->submissions()->create([
            'data' => ['name' => 'Test User'],
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

        $service = new PdfGeneratorService();
        $resultPath = $service->generate($form, $submission, $integration);

        expect($resultPath)->toStartWith('pdf-generated/');
        expect($resultPath)->toEndWith('.pdf');
        expect(Storage::exists($resultPath))->toBeTrue();

        // Verify it's a valid PDF
        $content = Storage::get($resultPath);
        expect($content)->toStartWith('%PDF');
    });

    it('generates pdf with zone mappings', function () {
        $pdfContent = createTestPdf();
        $templatePath = 'pdf-templates/1/template.pdf';
        Storage::put($templatePath, $pdfContent);

        $form = createTestForm([
            'properties' => [
                [
                    'id' => 'field_name',
                    'name' => 'Name',
                    'type' => 'text',
                ],
            ],
        ]);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $submission = $form->submissions()->create([
            'data' => ['field_name' => 'John Doe'],
        ]);

        $integration = FormIntegration::create([
            'form_id' => $form->id,
            'integration_id' => 'pdf',
            'status' => 'active',
            'data' => [
                'template_id' => $template->id,
                'zone_mappings' => [
                    [
                        'id' => 'zone_1',
                        'page' => 1,
                        'x' => 10,
                        'y' => 20,
                        'width' => 50,
                        'height' => 10,
                        'field_id' => 'field_name',
                        'font_size' => 12,
                        'font_color' => '#FF0000',
                    ],
                ],
                'filename_pattern' => 'output.pdf',
            ],
        ]);

        $service = new PdfGeneratorService();
        $resultPath = $service->generate($form, $submission, $integration);

        expect(Storage::exists($resultPath))->toBeTrue();

        $content = Storage::get($resultPath);
        expect($content)->toStartWith('%PDF');
    });

    it('handles special fields in zone mappings', function () {
        $pdfContent = createTestPdf();
        $templatePath = 'pdf-templates/1/template.pdf';
        Storage::put($templatePath, $pdfContent);

        $form = createTestForm(['title' => 'Contact Form']);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $submission = $form->submissions()->create([
            'data' => [],
        ]);

        $integration = FormIntegration::create([
            'form_id' => $form->id,
            'integration_id' => 'pdf',
            'status' => 'active',
            'data' => [
                'template_id' => $template->id,
                'zone_mappings' => [
                    [
                        'id' => 'zone_form_name',
                        'page' => 1,
                        'x' => 10,
                        'y' => 10,
                        'width' => 50,
                        'height' => 10,
                        'field_id' => 'form_name',
                        'font_size' => 12,
                        'font_color' => '#000000',
                    ],
                    [
                        'id' => 'zone_submission_id',
                        'page' => 1,
                        'x' => 10,
                        'y' => 20,
                        'width' => 50,
                        'height' => 10,
                        'field_id' => 'submission_id',
                        'font_size' => 12,
                        'font_color' => '#000000',
                    ],
                    [
                        'id' => 'zone_submission_date',
                        'page' => 1,
                        'x' => 10,
                        'y' => 30,
                        'width' => 50,
                        'height' => 10,
                        'field_id' => 'submission_date',
                        'font_size' => 12,
                        'font_color' => '#000000',
                    ],
                ],
                'filename_pattern' => 'output.pdf',
            ],
        ]);

        $service = new PdfGeneratorService();
        $resultPath = $service->generate($form, $submission, $integration);

        expect(Storage::exists($resultPath))->toBeTrue();
    });

    it('uses default filename pattern when not specified', function () {
        $pdfContent = createTestPdf();
        $templatePath = 'pdf-templates/1/template.pdf';
        Storage::put($templatePath, $pdfContent);

        $form = createTestForm(['title' => 'My Form']);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $submission = $form->submissions()->create([
            'data' => [],
        ]);

        $integration = FormIntegration::create([
            'form_id' => $form->id,
            'integration_id' => 'pdf',
            'status' => 'active',
            'data' => [
                'template_id' => $template->id,
                'zone_mappings' => [],
                // No filename_pattern - should use default
            ],
        ]);

        $service = new PdfGeneratorService();
        $resultPath = $service->generate($form, $submission, $integration);

        expect(Storage::exists($resultPath))->toBeTrue();
    });
});

describe('PdfNotSupportedException', function () {
    it('has correct default message', function () {
        $exception = new PdfNotSupportedException();

        expect($exception->getMessage())->toContain('PDF');
        expect($exception->getMessage())->toContain('compression');
    });

    it('accepts custom message', function () {
        $exception = new PdfNotSupportedException('Custom error message');

        expect($exception->getMessage())->toBe('Custom error message');
    });
});

/**
 * Helper to create a valid test PDF.
 */
function createTestPdf(): string
{
    $pdf = new \setasign\Fpdi\Fpdi();
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Cell(0, 10, 'Test PDF');

    return $pdf->Output('S');
}
