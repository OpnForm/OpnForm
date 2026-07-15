<?php

use App\Models\PdfTemplate;
use App\Service\Pdf\Modern\ModernPdfParser;
use App\Service\Pdf\PdfGeneratorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\FpdiPdfParser\PdfParser\PdfParser;

beforeEach(function () {
    Storage::fake('local');
});

describe('Modern PDF parser compatibility', function () {
    it('uses the FPDI parser shim globally', function () {
        $path = tempnam(sys_get_temp_dir(), 'modern_pdf_');
        file_put_contents($path, createModernPdfWithXrefStreamAndObjectStream());

        $pdf = new class () extends Fpdi {
            public function parserFor(string $filePath)
            {
                $this->setSourceFile($filePath);

                return $this->getPdfReader($this->currentReaderId)->getParser();
            }
        };

        try {
            $parser = $pdf->parserFor($path);
        } finally {
            @unlink($path);
        }

        expect($parser::class)->toBe(PdfParser::class);
        expect($parser)->toBeInstanceOf(ModernPdfParser::class);
    });

    it('loads a PDF with xref streams and compressed object streams through plain FPDI', function () {
        $path = tempnam(sys_get_temp_dir(), 'modern_pdf_');
        file_put_contents($path, createModernPdfWithXrefStreamAndObjectStream());

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($path);
            $templateId = $pdf->importPage(1);
            $size = $pdf->getTemplateSize($templateId);

            expect($pageCount)->toBe(1);
            expect(round($size['width'], 1))->toBe(215.9);
            expect(round($size['height'], 1))->toBe(279.4);
        } finally {
            @unlink($path);
        }
    });

    it('accepts modern PDFs during template upload page counting', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);
        $file = UploadedFile::fake()->createWithContent(
            'modern-template.pdf',
            createModernPdfWithXrefStreamAndObjectStream(),
        );

        $response = $this->postJson(
            route('open.forms.pdf-templates.store', $form),
            ['file' => $file],
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.page_count', 1);
    });

    it('generates submission PDFs from modern PDF templates', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace, [
            'properties' => [
                ['id' => 'name', 'name' => 'Name', 'type' => 'text'],
            ],
        ]);

        $pdfContent = createModernPdfWithXrefStreamAndObjectStream();
        $templatePath = "pdf-templates/{$form->id}/modern-template.pdf";
        Storage::put($templatePath, $pdfContent);

        $template = PdfTemplate::create([
            'form_id' => $form->id,
            'name' => 'Modern Template',
            'filename' => 'modern-template.pdf',
            'original_filename' => 'Modern Template.pdf',
            'file_path' => $templatePath,
            'file_size' => strlen($pdfContent),
            'page_count' => 1,
            'page_manifest' => [
                ['id' => 'page-1', 'type' => 'source', 'source_page' => 1],
            ],
            'zone_mappings' => [
                [
                    'id' => 'zone-name',
                    'page_id' => 'page-1',
                    'x' => 10,
                    'y' => 10,
                    'width' => 50,
                    'height' => 10,
                    'field_id' => 'name',
                    'font_size' => 12,
                    'font_color' => '#000000',
                ],
            ],
            'filename_pattern' => PdfTemplate::DEFAULT_FILENAME_PATTERN,
        ]);
        $submission = $form->submissions()->create([
            'data' => ['name' => 'Alice'],
        ]);

        $resultPath = (new PdfGeneratorService())->generateFromTemplate($form, $submission, $template);

        expect(Storage::exists($resultPath))->toBeTrue();
        expect(Storage::get($resultPath))->toStartWith('%PDF');
    });
});

function createModernPdfWithXrefStreamAndObjectStream(): string
{
    $pdf = "%PDF-1.6\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];

    $addObject = function (int $number, string $body) use (&$pdf, &$offsets): void {
        $offsets[$number] = strlen($pdf);
        $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');

    $pageContent = "BT\n/F1 12 Tf\n72 720 Td\n(Modern PDF) Tj\nET\n";
    $addObject(4, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}endstream");

    $pagesObject = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $pageObject = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> /Contents 4 0 R >>';
    $objectStreamBody = $pagesObject . "\n" . $pageObject;
    $objectStreamHeader = '2 0 3 ' . (strlen($pagesObject) + 1) . ' ';
    $objectStreamData = $objectStreamHeader . $objectStreamBody;
    $compressedObjectStream = gzcompress($objectStreamData);

    $addObject(
        10,
        '<< /Type /ObjStm /N 2 /First ' . strlen($objectStreamHeader)
            . ' /Length ' . strlen($compressedObjectStream)
            . " /Filter /FlateDecode >>\nstream\n{$compressedObjectStream}\nendstream",
    );

    $xrefOffset = strlen($pdf);
    $xrefEntry = fn (int $type, int $field2, int $field3): string => pack('CNn', $type, $field2, $field3);
    $xrefData = ''
        . $xrefEntry(0, 0, 65535)
        . $xrefEntry(1, $offsets[1], 0)
        . $xrefEntry(2, 10, 0)
        . $xrefEntry(2, 10, 1)
        . $xrefEntry(1, $offsets[4], 0)
        . $xrefEntry(1, $xrefOffset, 0)
        . $xrefEntry(1, $offsets[10], 0);
    $compressedXref = gzcompress($xrefData);

    $pdf .= "5 0 obj\n"
        . '<< /Type /XRef /Size 11 /Root 1 0 R /Index [0 6 10 1] /W [1 4 2] /Length '
        . strlen($compressedXref)
        . " /Filter /FlateDecode >>\nstream\n{$compressedXref}\nendstream\nendobj\n";
    $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

    return $pdf;
}
