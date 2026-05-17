<?php

namespace App\Service\Pdf;

use App\Exceptions\PdfNotSupportedException;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;

/**
 * Rebuilds template PDF files from explicit logical page manifests.
 */
class PdfTemplateRebuildService
{
    /**
     * Rebuild a PDF file from an explicit page manifest.
     *
     * @param  string  $currentFilePath
     * @param  array<int, array{id:string,type:string,source_page:?int}>  $pageManifest
     *
     * @throws PdfNotSupportedException
     */
    public function rebuildFromManifest(string $currentFilePath, array $pageManifest): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_rebuild_manifest_');
        try {
            file_put_contents($tempFile, Storage::get($currentFilePath));
            $pdf = new Fpdi();
            $sourcePageCount = $pdf->setSourceFile($tempFile);
            if ($sourcePageCount < 1) {
                throw new PdfNotSupportedException('Invalid source PDF.');
            }

            $firstTemplateId = $pdf->importPage(1);
            $firstSize = $pdf->getTemplateSize($firstTemplateId);

            foreach ($pageManifest as $entry) {
                $type = $entry['type'] ?? null;
                $sourcePage = isset($entry['source_page']) ? (int) $entry['source_page'] : null;

                if ($type === 'blank') {
                    $pdf->AddPage($firstSize['orientation'], [$firstSize['width'], $firstSize['height']]);
                    continue;
                }

                if ($sourcePage === null || $sourcePage < 1 || $sourcePage > $sourcePageCount) {
                    throw new PdfNotSupportedException('Invalid source_page in page manifest.');
                }

                $templateId = $pdf->importPage($sourcePage);
                $size = $pdf->getTemplateSize($templateId);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);
            }

            return $pdf->Output('S');
        } catch (CrossReferenceException $e) {
            throw new PdfNotSupportedException();
        } finally {
            @unlink($tempFile);
        }
    }
}
