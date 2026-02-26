<?php

namespace App\Service\Pdf;

use App\Exceptions\PdfNotSupportedException;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;

/**
 * Rebuilds the template PDF file on save: remove pages and/or insert new pages.
 * The result is written back to storage so the stored file matches page_count and structure.
 */
class PdfTemplateRebuildService
{
    /**
     * Rebuild PDF: apply removed_pages then insert new_pages. Returns new PDF content.
     *
     * @param  string  $currentFilePath  Path in storage to current PDF
     * @param  array<int>  $removedPages  1-based logical page numbers to remove (applied in order)
     * @param  array<int>  $newPages  1-based logical positions where to insert new blank pages (in final document)
     * @param  int  $targetPageCount  Expected final page count
     * @return string New PDF binary content
     *
     * @throws PdfNotSupportedException
     */
    public function rebuild(
        string $currentFilePath,
        array $removedPages,
        array $newPages,
        int $targetPageCount
    ): string {
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_rebuild_');
        try {
            file_put_contents($tempFile, Storage::get($currentFilePath));
            $pdf = new Fpdi();
            $sourcePageCount = $pdf->setSourceFile($tempFile);

            // Step 1: build intermediate PDF with removed pages (if any)
            $afterRemoval = $tempFile;
            $afterRemovalCount = $sourcePageCount;
            if (! empty($removedPages)) {
                $afterRemoval = $this->removePages($tempFile, $removedPages, $sourcePageCount);
                $afterRemovalCount = $sourcePageCount - count($removedPages);
            }

            // Step 2: build final PDF with new pages inserted (if any)
            if (! empty($newPages)) {
                $content = $this->insertNewPages($afterRemoval, $afterRemovalCount, $newPages, $targetPageCount);
                if ($afterRemoval !== $tempFile) {
                    @unlink($afterRemoval);
                }
                return $content;
            }

            if ($afterRemoval !== $tempFile) {
                $content = file_get_contents($afterRemoval);
                @unlink($afterRemoval);
                return $content;
            }

            return file_get_contents($tempFile);
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Remove pages at 1-based logical positions (in order). Each removal applies to the current page list.
     * Returns path to new temp file.
     */
    private function removePages(string $sourcePath, array $removedPages, int $sourcePageCount): string
    {
        try {
            // Build list of physical page numbers to keep (1-based). Apply removals in order.
            $pagesToKeep = range(1, $sourcePageCount);
            foreach ($removedPages as $r) {
                // r is 1-based logical index into current list
                $idx = $r - 1;
                if ($idx >= 0 && $idx < count($pagesToKeep)) {
                    array_splice($pagesToKeep, $idx, 1);
                }
            }
            if (empty($pagesToKeep)) {
                throw new PdfNotSupportedException('Cannot remove all pages.');
            }
            // Use a single Fpdi instance: importPage and useTemplate must be on the same instance
            $pdf = new Fpdi();
            $pdf->setSourceFile($sourcePath);
            $outFile = tempnam(sys_get_temp_dir(), 'pdf_after_removal_');
            foreach ($pagesToKeep as $physicalPage) {
                $tid = $pdf->importPage($physicalPage);
                $size = $pdf->getTemplateSize($tid);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tid, 0, 0, $size['width'], $size['height']);
            }
            $pdf->Output('F', $outFile);
            return $outFile;
        } catch (CrossReferenceException $e) {
            throw new PdfNotSupportedException();
        }
    }

    /**
     * Insert new (blank) pages at given 1-based logical positions. Source file has sourcePageCount pages.
     */
    private function insertNewPages(
        string $sourcePath,
        int $sourcePageCount,
        array $newPages,
        int $targetPageCount
    ): string {
        try {
            // Use a single Fpdi instance: importPage and useTemplate must be on the same instance
            $pdf = new Fpdi();
            $pdf->setSourceFile($sourcePath);
            $firstTid = $pdf->importPage(1);
            $firstSize = $pdf->getTemplateSize($firstTid);
            $outFile = tempnam(sys_get_temp_dir(), 'pdf_after_new_');
            $physical = 1;
            for ($logical = 1; $logical <= $targetPageCount; $logical++) {
                if (in_array($logical, $newPages, true)) {
                    $pdf->AddPage($firstSize['orientation'], [$firstSize['width'], $firstSize['height']]);
                } else {
                    if ($physical <= $sourcePageCount) {
                        $tid = $pdf->importPage($physical);
                        $size = $pdf->getTemplateSize($tid);
                        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                        $pdf->useTemplate($tid, 0, 0, $size['width'], $size['height']);
                    }
                    $physical++;
                }
            }
            $pdf->Output('F', $outFile);
            $content = file_get_contents($outFile);
            @unlink($outFile);
            return $content;
        } catch (CrossReferenceException $e) {
            throw new PdfNotSupportedException();
        }
    }
}
