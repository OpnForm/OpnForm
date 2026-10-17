<?php

use App\Exceptions\PdfNotSupportedException;
use App\Models\Forms\FormSubmission;
use App\Models\PdfTemplate;
use App\Service\Pdf\PdfDevanagariTextRenderer;
use App\Service\Pdf\PdfGeneratorService;
use App\Service\Pdf\PdfRichTextRenderer;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;

it('detects Devanagari text including HTML entities without changing Latin text', function () {
    expect(PdfDevanagariTextRenderer::isRequired('प्रशिक्षण अर्ज'))->toBeTrue()
        ->and(PdfDevanagariTextRenderer::isRequired('&#2350;&#2352;&#2366;&#2336;&#2368;'))->toBeTrue()
        ->and(PdfDevanagariTextRenderer::isRequired('Délivrée été'))->toBeFalse();
});

it('reports an unavailable Devanagari renderer instead of silently corrupting text', function () {
    config(['opnform.pdf.pango_binary' => null]);
    $pdf = new Fpdi();
    $pdf->AddPage();

    expect(fn () => (new PdfRichTextRenderer())->render(
        $pdf,
        'प्रशिक्षण अर्ज',
        10,
        10,
        80,
        15,
        ['font_size' => 12],
        210
    ))->toThrow(PdfNotSupportedException::class, 'required text renderer is not enabled');
});

it('embeds shaped text and keeps the original source for subsequent template pages', function () {
    $binary = getenv('PDF_TEST_PANGO_BINARY');
    if (! $binary || ! is_executable($binary)) {
        $this->markTestSkipped('Set PDF_TEST_PANGO_BINARY to run the native Pango/font integration check.');
    }
    config(['opnform.pdf.pango_binary' => $binary]);
    Storage::fake('local');

    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $source = new Fpdi();
    for ($page = 1; $page <= 3; $page++) {
        $source->AddPage();
        $source->SetFont('Helvetica', '', 12);
        $source->Text(10, 10, 'ORIGINAL PAGE '.$page);
    }
    Storage::put('source.pdf', $source->Output('S'));
    $manifest = PdfTemplate::buildDefaultPageManifest(3);
    $template = PdfTemplate::create([
        'form_id' => $form->id, 'name' => 'Marathi test', 'filename' => 'source.pdf',
        'original_filename' => 'source.pdf', 'file_path' => 'source.pdf', 'page_count' => 3,
        'file_size' => strlen(Storage::get('source.pdf')),
        'page_manifest' => $manifest,
        'zone_mappings' => array_map(fn ($page) => [
            'page_id' => $page['id'], 'x' => 5, 'y' => 10, 'width' => 80, 'height' => 12,
            'static_text' => '<p>मराठी भाषा: <b>प्रशिक्षण अर्ज</b>, क्ष आणि ज्ञ &lt;span&gt; &amp;</p>',
            'font_size' => 14, 'font_color' => '#153f68',
        ], $manifest),
    ]);
    $submission = new FormSubmission(['form_id' => $form->id, 'data' => []]);
    $submission->id = 0;
    $path = (new PdfGeneratorService())->generateFromTemplate($form, $submission, $template);
    $output = Storage::get($path);
    expect($output)->toStartWith('%PDF')->and(strlen($output))->toBeLessThan(150000);

    $read = new Fpdi();
    expect($read->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($output)))->toBe(3);

    $text = new Process(['pdftotext', '-', '-']);
    $text->setInput($output)->mustRun();
    expect($text->getOutput())->toContain('ORIGINAL PAGE 1', 'ORIGINAL PAGE 2', 'ORIGINAL PAGE 3', 'प्रशिक्षण अर्ज', 'क्ष आणि ज्ञ', '<span>', '&');
    expect(substr_count($text->getOutput(), 'प्रशिक्षण अर्ज'))->toBe(3);
    expect($text->getErrorOutput())->toBe('');
    $fonts = new Process(['pdffonts', '-']);
    $fonts->setInput($output)->mustRun();
    expect($fonts->getOutput())->toContain('NotoSansDevanagari-Regular', 'NotoSans-Regular')->not->toContain('Kohinoor');
});
