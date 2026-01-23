<?php

namespace App\Service\Pdf;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\Integration\FormIntegration;
use App\Models\PdfTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PdfCacheService
{
    private const CACHE_TTL = 3600; // 1 hour

    private const LOCK_TTL = 30; // 30 seconds max lock time

    /**
     * Get cached PDF path or generate a new one.
     * Uses locking to prevent duplicate generation from concurrent requests.
     */
    public function getOrGenerate(
        Form $form,
        FormSubmission $submission,
        FormIntegration $integration,
        PdfGeneratorService $generator
    ): string {
        $cacheKey = $this->getCacheKey($form, $submission, $integration);

        // Check if we have a cached path
        $cachedPath = Cache::get($cacheKey);

        if ($cachedPath && Storage::exists($cachedPath)) {
            return $cachedPath;
        }

        // Use atomic lock to prevent concurrent PDF generation
        $lockKey = $cacheKey . ':lock';

        return Cache::lock($lockKey, self::LOCK_TTL)->block(self::LOCK_TTL, function () use ($cacheKey, $form, $submission, $integration, $generator) {
            // Check cache again after acquiring lock (another request may have generated it)
            $cachedPath = Cache::get($cacheKey);
            if ($cachedPath && Storage::exists($cachedPath)) {
                return $cachedPath;
            }

            // Generate new PDF
            $pdfPath = $generator->generate($form, $submission, $integration);

            // Cache the path
            Cache::put($cacheKey, $pdfPath, self::CACHE_TTL);

            return $pdfPath;
        });
    }

    /**
     * Invalidate cache for a specific integration.
     */
    public function invalidate(Form $form, FormIntegration $integration): void
    {
        // We can't easily invalidate all submissions for this integration
        // The cache will naturally expire after TTL
        // For immediate invalidation, we rely on the template updated_at in the cache key
    }

    /**
     * Generate a unique cache key for this PDF.
     * Includes template updated_at to automatically invalidate when template changes.
     */
    private function getCacheKey(
        Form $form,
        FormSubmission $submission,
        FormIntegration $integration
    ): string {
        $data = $integration->data;
        $templateId = $data->template_id ?? 0;

        // Get template updated_at for automatic invalidation
        $template = PdfTemplate::find($templateId);
        $templateVersion = $template ? $template->updated_at->timestamp : 0;

        return sprintf(
            'pdf:%d:%d:%d:%d',
            $form->id,
            $submission->id,
            $integration->id,
            $templateVersion
        );
    }

    /**
     * Clean up old cached PDFs.
     */
    public function cleanup(): void
    {
        // This would be run via a scheduled command
        // Delete files older than cache TTL
        $files = Storage::files('pdf-generated');

        foreach ($files as $file) {
            $lastModified = Storage::lastModified($file);
            if (time() - $lastModified > self::CACHE_TTL) {
                Storage::delete($file);
            }
        }
    }
}
