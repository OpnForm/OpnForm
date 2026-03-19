<?php

use App\Exceptions\PdfNotSupportedException;
use App\Models\Forms\Form;
use App\Models\PdfTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Service\Pdf\PdfContentRenderer;
use App\Service\Pdf\PdfGeneratorService;
use App\Service\Pdf\PdfImageResolver;
use Illuminate\Support\Facades\Http;
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
        // Zone mappings and filename_pattern are stored on the template
        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
            'zone_mappings' => [],
            'filename_pattern' => PdfTemplate::DEFAULT_FILENAME_PATTERN,
        ]);

        $submission = $form->submissions()->create([
            'data' => ['name' => 'Test User'],
        ]);

        $service = new PdfGeneratorService();
        $resultPath = $service->generateFromTemplate($form, $submission, $template);

        expect($resultPath)->toStartWith('tmp/pdf-output/');
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

        // Zone mappings are now stored on the template
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
            'filename_pattern' => 'output',
        ]);

        $submission = $form->submissions()->create([
            'data' => ['field_name' => 'John Doe'],
        ]);

        $service = new PdfGeneratorService();
        $resultPath = $service->generateFromTemplate($form, $submission, $template);

        expect(Storage::exists($resultPath))->toBeTrue();

        $content = Storage::get($resultPath);
        expect($content)->toStartWith('%PDF');
    });

    it('handles special fields in zone mappings', function () {
        $pdfContent = createTestPdf();
        $templatePath = 'pdf-templates/1/template.pdf';
        Storage::put($templatePath, $pdfContent);

        $form = createTestForm(['title' => 'Contact Form']);

        // Zone mappings with special fields are stored on the template
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
            'filename_pattern' => 'output',
        ]);

        $submission = $form->submissions()->create([
            'data' => [],
        ]);

        $service = new PdfGeneratorService();
        $resultPath = $service->generateFromTemplate($form, $submission, $template);

        expect(Storage::exists($resultPath))->toBeTrue();
    });

    it('uses default filename pattern when not specified', function () {
        $pdfContent = createTestPdf();
        $templatePath = 'pdf-templates/1/template.pdf';
        Storage::put($templatePath, $pdfContent);

        $form = createTestForm(['title' => 'My Form']);

        // Template without explicit filename_pattern
        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Test Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
            'zone_mappings' => [],
            // No filename_pattern - should use default
        ]);

        $submission = $form->submissions()->create([
            'data' => [],
        ]);

        $service = new PdfGeneratorService();
        $resultPath = $service->generateFromTemplate($form, $submission, $template);

        expect(Storage::exists($resultPath))->toBeTrue();
    });

    it('generates pdf with static image from unsplash-like url', function () {
        Http::fake([
            'https://images.unsplash.com/*' => Http::response(
                tinyPngBytes(),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        $pdfContent = createTestPdf();
        $templatePath = 'pdf-templates/1/template.pdf';
        Storage::put($templatePath, $pdfContent);

        $form = createTestForm();

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Image URL Template',
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
            'page_manifest' => [
                ['id' => 'page-1', 'type' => 'source', 'source_page' => 1],
            ],
            'zone_mappings' => [
                [
                    'id' => 'zone_static_image',
                    'page_id' => 'page-1',
                    'x' => 10,
                    'y' => 10,
                    'width' => 20,
                    'height' => 20,
                    'static_image' => 'https://images.unsplash.com/photo-12345?auto=format&fit=crop&w=900&q=80',
                ],
            ],
            'filename_pattern' => 'output',
        ]);

        $submission = $form->submissions()->create([
            'data' => [],
        ]);

        $service = new PdfGeneratorService();
        $resultPath = $service->generateFromTemplate($form, $submission, $template);

        expect(Storage::exists($resultPath))->toBeTrue();
        expect(Storage::get($resultPath))->toStartWith('%PDF');
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

describe('Image resolving', function () {
    it('resolves image content from storage for form uploads', function () {
        $form = createTestForm();
        $fileName = 'avatar-test.png';
        Storage::put("forms/{$form->id}/submissions/{$fileName}", 'img-bytes');

        $resolver = new PdfImageResolver($form);
        $content = $resolver->resolveContent($fileName);

        expect($content)->toBe('img-bytes');
    });

    it('fetches remote image urls when reachable and image content-type', function () {
        Http::fake([
            'https://example.com/*' => Http::response(
                tinyPngBytes(),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        $resolver = new PdfImageResolver();

        $content = $resolver->resolveContent('https://example.com/image.png');

        expect($content)->toBe(tinyPngBytes());
    });

    it('returns null for remote urls with non-image content-type', function () {
        Http::fake([
            'https://example.com/*' => Http::response(
                '<html>not an image</html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $resolver = new PdfImageResolver();
        $content = $resolver->resolveContent('https://example.com/image.png');

        expect($content)->toBeNull();
    });
});

describe('PdfContentRenderer scalar values', function () {
    it('renders numeric values without dropping them', function () {
        $renderer = PdfContentRenderer::forForm(null);
        $pdf = new \setasign\Fpdi\Fpdi();
        $pdf->AddPage();

        $renderer->renderContent(
            $pdf,
            12345,
            10,
            10,
            80,
            20,
            ['font_size' => 12, 'font_color' => '#000000'],
            210
        );

        $content = $pdf->Output('S');
        expect($content)->toStartWith('%PDF');
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

function tinyPngBytes(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAgMBAQEAAP8AAAAASUVORK5CYII=',
        true
    ) ?: '';
}
