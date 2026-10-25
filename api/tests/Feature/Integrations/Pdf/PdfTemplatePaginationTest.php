<?php

use App\Models\Forms\Form;
use App\Models\PdfTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Service\Pdf\PdfGeneratorService;
use App\Service\Pdf\PdfCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReader;

it('keeps bottom zones and subsequent upper zones on their logical template pages', function (bool $multiplePages) {
    Storage::fake('local');
    $source = new Fpdi();
    $source->AddPage('P', 'A4');
    $source->SetFont('Helvetica', '', 12);
    $source->Text(20, 20, 'Source one');
    $source->AddPage('L', 'A4');
    $source->Text(20, 20, 'Source two');
    $sourceContent = $source->Output('S');
    Storage::put('pagination-template.pdf', $sourceContent);

    $user = User::factory()->create();
    $workspace = Workspace::create(['name' => 'Pagination workspace']);
    $user->workspaces()->attach($workspace->id, ['role' => 'admin']);
    $form = Form::factory()->forWorkspace($workspace)->createdBy($user)
        ->withProperties([['id' => 'footer', 'name' => 'Footer', 'type' => 'text']])->create();
    $manifest = [['id' => 'first', 'type' => 'source', 'source_page' => 1]];
    if ($multiplePages) {
        $manifest[] = ['id' => 'blank', 'type' => 'blank'];
        $manifest[] = ['id' => 'last', 'type' => 'source', 'source_page' => 2];
    }
    $zones = [];
    foreach ($manifest as $index => $page) {
        $number = $index + 1;
        $zones[] = [
            'id' => "footer-{$number}", 'page_id' => $page['id'], 'field_id' => 'footer',
            'x' => 10, 'y' => 95, 'width' => 50, 'height' => 3, 'font_size' => 10,
        ];
        // Zone order is intentional: editing a lower zone must not move later zones to a new page.
        $zones[] = [
            'id' => "upper-{$number}", 'page_id' => $page['id'],
            'static_text' => "<b>Upper{$number}</b>\nSecond{$number}\nClipped{$number}",
            'x' => 40, 'y' => 10, 'width' => 40, 'height' => 3, 'font_size' => 10,
        ];
    }
    $template = PdfTemplate::create([
        'form_id' => $form->id, 'name' => 'Pagination template', 'filename' => 'template.pdf',
        'original_filename' => 'template.pdf', 'file_path' => 'pagination-template.pdf',
        'file_size' => strlen($sourceContent), 'page_count' => 2,
        'page_manifest' => $manifest, 'zone_mappings' => $zones,
    ]);
    $submission = $form->submissions()->create(['data' => ['footer' => 'Footer']]);

    $path = (new PdfGeneratorService())->generateFromTemplate($form, $submission, $template);
    $reader = new PdfReader(new PdfParser(StreamReader::createByString(Storage::get($path))));
    expect($reader->getPageCount())->toBe(count($manifest));
    foreach ($manifest as $index => $entry) {
        $number = $index + 1;
        $page = $reader->getPage($number);
        $stream = $page->getContentStream();
        expect($stream)->toContain('(Footer)', "(Upper{$number})")
            ->not->toContain("(Clipped{$number})");
        $dimensions = $page->getWidthAndHeight();
        $landscape = $entry['source_page'] ?? null;
        if ($landscape === 2) {
            expect($dimensions[0])->toBeGreaterThan($dimensions[1]);
        }
        if ($landscape !== 2) {
            expect($dimensions[1])->toBeGreaterThan($dimensions[0]);
            expect($stream)->toContain("(Second{$number})");
        }
        // FPDF text coordinates use a bottom-left origin, with its standard cell inset.
        preg_match('/BT ([0-9.]+) ([0-9.]+) Td \(Upper' . $number . '\) Tj ET/', $stream, $position);
        expect($position)->toHaveCount(3);
        expect((float) $position[1])->toEqualWithDelta($dimensions[0] * 0.4 + 2.835, 0.02);
        expect((float) $position[2])->toEqualWithDelta($dimensions[1] * 0.9 - 8.67, 0.02);
        expect($stream)->toMatch('/\/F[0-9]+ 10\.00 Tf/');
    }
})->with(['single page' => [false], 'source, blank and landscape pages' => [true]]);


it('bypasses previously cached PDFs after the pagination change and reuses the corrected PDF', function () {
    Storage::fake('local');
    $form = new Form(['computed_variables' => []]);
    $form->id = 11;
    $submission = new \App\Models\Forms\FormSubmission();
    $submission->id = 22;
    $template = new PdfTemplate();
    $template->id = 33;
    $template->updated_at = now();
    $legacySuffix = sprintf(
        '%d:%d:%d:%d:%s',
        $form->id,
        $submission->id,
        $template->id,
        $template->updated_at->timestamp,
        hash('sha256', serialize([]))
    );
    Storage::put('tmp/pdf-output/legacy.pdf', 'old pagination');
    Storage::put('tmp/pdf-output/corrected.pdf', 'fixed pagination');
    foreach (['pdf:', 'pdf:text-v2:'] as $prefix) {
        Cache::put($prefix . $legacySuffix, 'tmp/pdf-output/legacy.pdf', 3600);
    }
    $generator = Mockery::mock(PdfGeneratorService::class);
    $generator->shouldReceive('generateFromTemplate')->once()->with($form, $submission, $template)
        ->andReturn('tmp/pdf-output/corrected.pdf');
    $cache = new PdfCacheService();

    expect($cache->getOrGenerateFromTemplate($form, $submission, $template, $generator))
        ->toBe('tmp/pdf-output/corrected.pdf');
    expect($cache->getOrGenerateFromTemplate($form, $submission, $template, $generator))
        ->toBe('tmp/pdf-output/corrected.pdf');
});
