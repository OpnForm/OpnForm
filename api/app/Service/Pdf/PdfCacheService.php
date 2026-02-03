<?php

namespace App\Service\Pdf;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\PdfTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PdfCacheService
{
    private const CACHE_TTL = 3600; // 1 hour

    private const LOCK_TTL = 30; // 30 seconds max lock time

    // Use same temp folder as generator for lifecycle cleanup
    private const TEMP_FOLDER = 'tmp/pdf-output';

    /**
     * Get cached PDF path or generate a new one using a template.
     * Uses locking to prevent duplicate generation from concurrent requests.
     */
    public function getOrGenerateFromTemplate(
        Form $form,
        FormSubmission $submission,
        PdfTemplate $template,
        PdfGeneratorService $generator
    ): string {
        $cacheKey = $this->getTemplateCacheKey($form, $submission, $template);

        // Check if we have a cached path
        $cachedPath = Cache::get($cacheKey);

        if ($cachedPath && Storage::exists($cachedPath)) {
            return $cachedPath;
        }

        // Use atomic lock to prevent concurrent PDF generation
        $lockKey = $cacheKey . ':lock';

        return Cache::lock($lockKey, self::LOCK_TTL)->block(self::LOCK_TTL, function () use ($cacheKey, $form, $submission, $template, $generator) {
            // Check cache again after acquiring lock (another request may have generated it)
            $cachedPath = Cache::get($cacheKey);
            if ($cachedPath && Storage::exists($cachedPath)) {
                return $cachedPath;
            }

            // Generate new PDF from template
            $pdfPath = $generator->generateFromTemplate($form, $submission, $template);

            // Cache the path
            Cache::put($cacheKey, $pdfPath, self::CACHE_TTL);

            return $pdfPath;
        });
    }

    /**
     * Invalidate cache for a specific template.
     */
    public function invalidateTemplate(PdfTemplate $template): void
    {
        // We can't easily invalidate all submissions for this template
        // The cache will naturally expire after TTL
        // For immediate invalidation, we rely on the template updated_at in the cache key
    }

    /**
     * Generate a unique cache key for template-based PDF.
     * Includes template updated_at to automatically invalidate when template changes.
     */
    private function getTemplateCacheKey(
        Form $form,
        FormSubmission $submission,
        PdfTemplate $template
    ): string {
        $templateVersion = $template->updated_at->timestamp;

        return sprintf(
            'pdf:%d:%d:%d:%d',
            $form->id,
            $submission->id,
            $template->id,
            $templateVersion
        );
    }

    /**
     * Clean up old cached PDFs.
     */
    public function cleanup(): void
    {
        // This would be run via a scheduled command
        // Delete files older than cache TTL from the consistent temp folder
        $files = Storage::files(self::TEMP_FOLDER);

        foreach ($files as $file) {
            $lastModified = Storage::lastModified($file);
            if (time() - $lastModified > self::CACHE_TTL) {
                Storage::delete($file);
            }
        }
    }
}
