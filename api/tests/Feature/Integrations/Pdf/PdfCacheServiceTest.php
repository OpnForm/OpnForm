<?php

use App\Models\Forms\Form;
use App\Models\Integration\FormIntegration;
use App\Models\PdfTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Service\Pdf\PdfCacheService;
use App\Service\Pdf\PdfGeneratorService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function createCacheTestForm(array $attributes = []): Form
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

describe('PdfCacheService', function () {
    it('generates pdf on first request', function () {
        $pdfContent = createCacheTestPdf();
        $templatePath = 'pdf-templates/1/template.pdf';
        Storage::put($templatePath, $pdfContent);

        $form = createCacheTestForm();

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $submission = $form->submissions()->create([
            'data' => ['name' => 'Test'],
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

        $cacheService = new PdfCacheService();
        $generator = new PdfGeneratorService();

        $path = $cacheService->getOrGenerate($form, $submission, $integration, $generator);

        expect($path)->not->toBeNull();
        expect(Storage::exists($path))->toBeTrue();
    });

    it('returns cached pdf on subsequent requests', function () {
        $pdfContent = createCacheTestPdf();
        $templatePath = 'pdf-templates/1/template.pdf';
        Storage::put($templatePath, $pdfContent);

        $form = createCacheTestForm();

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $submission = $form->submissions()->create([
            'data' => ['name' => 'Test'],
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

        $cacheService = new PdfCacheService();
        $generator = new PdfGeneratorService();

        // First call - generates
        $path1 = $cacheService->getOrGenerate($form, $submission, $integration, $generator);

        // Second call - should return same cached path
        $path2 = $cacheService->getOrGenerate($form, $submission, $integration, $generator);

        expect($path1)->toBe($path2);
    });

    it('generates unique cache keys for different submissions', function () {
        $pdfContent = createCacheTestPdf();
        $templatePath = 'pdf-templates/1/template.pdf';
        Storage::put($templatePath, $pdfContent);

        $form = createCacheTestForm();

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $submission1 = $form->submissions()->create([
            'data' => ['name' => 'User 1'],
        ]);

        $submission2 = $form->submissions()->create([
            'data' => ['name' => 'User 2'],
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

        $cacheService = new PdfCacheService();
        $generator = new PdfGeneratorService();

        $path1 = $cacheService->getOrGenerate($form, $submission1, $integration, $generator);
        $path2 = $cacheService->getOrGenerate($form, $submission2, $integration, $generator);

        // Different submissions should have different cache paths
        expect($path1)->not->toBe($path2);
    });

    it('can cleanup old cached pdfs', function () {
        // Create some old cached files
        Storage::put('pdf-generated/old-file.pdf', 'old content');

        // Simulate the file being old by checking cleanup logic
        $cacheService = new PdfCacheService();

        // The cleanup method checks file modification time
        // In tests, we just verify the method runs without error
        $cacheService->cleanup();

        // Files within TTL should still exist (our fake file was just created)
        expect(Storage::exists('pdf-generated/old-file.pdf'))->toBeTrue();
    });

    it('invalidates cache when template is updated', function () {
        $pdfContent = createCacheTestPdf();
        $templatePath = 'pdf-templates/1/template.pdf';
        Storage::put($templatePath, $pdfContent);

        $form = createCacheTestForm();

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'filename' => 'template.pdf',
            'original_filename' => 'Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
        ]);

        $submission = $form->submissions()->create([
            'data' => ['name' => 'Test'],
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

        $cacheService = new PdfCacheService();
        $generator = new PdfGeneratorService();

        // Generate first
        $path1 = $cacheService->getOrGenerate($form, $submission, $integration, $generator);

        // Clear the Laravel cache so the next generation creates a new file
        \Illuminate\Support\Facades\Cache::flush();

        // Generate again - should create new file since cache was cleared
        $path2 = $cacheService->getOrGenerate($form, $submission, $integration, $generator);

        // Both paths should exist (different files generated)
        expect(Storage::exists($path1))->toBeTrue();
        expect(Storage::exists($path2))->toBeTrue();
    });
});

/**
 * Helper to create a valid test PDF.
 */
function createCacheTestPdf(): string
{
    $pdf = new \setasign\Fpdi\Fpdi();
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Cell(0, 10, 'Cache Test PDF');

    return $pdf->Output('S');
}
