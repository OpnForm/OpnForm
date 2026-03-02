<?php

namespace App\Service\Pdf;

use App\Models\Forms\Form;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;

class PdfContentRenderer
{
    private readonly PdfImageResolver $imageResolver;
    private readonly PdfImageRenderer $imageRenderer;
    private readonly PdfRichTextRenderer $richTextRenderer;

    public function __construct(
        private readonly ?Form $form = null,
        ?PdfImageResolver $imageResolver = null,
        ?PdfImageRenderer $imageRenderer = null,
        ?PdfRichTextRenderer $richTextRenderer = null
    ) {
        $this->imageResolver = $imageResolver ?? new PdfImageResolver($form);
        $this->imageRenderer = $imageRenderer ?? new PdfImageRenderer();
        $this->richTextRenderer = $richTextRenderer ?? new PdfRichTextRenderer();
    }

    public static function forForm(?Form $form): self
    {
        return new self(
            $form,
            new PdfImageResolver($form),
            new PdfImageRenderer(),
            new PdfRichTextRenderer()
        );
    }

    public function renderContent(
        Fpdi $pdf,
        mixed $value,
        float $x,
        float $y,
        float $width,
        float $height,
        array $zone,
        float $pageWidth
    ): void {
        if ($value === null) {
            return;
        }

        if (!is_scalar($value)) {
            return;
        }

        $value = (string) $value;
        if ($value === '') {
            return;
        }

        $shouldRenderAsImage = array_key_exists('static_image', $zone) || $this->isImageReference($value);
        if ($shouldRenderAsImage) {
            $imageContent = $this->imageResolver->resolveContent($value);
            if ($imageContent !== null) {
                try {
                    $this->imageRenderer->render($pdf, $imageContent, $x, $y, $width, $height);
                    return;
                } catch (\Throwable $e) {
                    Log::debug('PDF image rendering failed', [
                        'form_id' => $this->form?->id,
                        'value' => $value,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->richTextRenderer->render($pdf, $value, $x, $y, $width, $height, $zone, $pageWidth);
    }

    private function isImageReference(string $value): bool
    {
        if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $value)) {
            return true;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
            $path = (string) parse_url($value, PHP_URL_PATH);
            return (bool) preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $path);
        }

        return false;
    }
}
