<?php

namespace App\Service\Pdf;

use App\Models\Forms\Form;
use App\Service\Storage\FileUploadPathService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

/**
 * Renders zone content (text or image) into PDF zones.
 * Handles HTML rich text (bold, italic, underline, h1, h2, color) and image URLs/storage paths.
 */
class PdfZoneRenderer
{
    private const DEFAULT_FONT_SIZE = 12;

    private const DEFAULT_FONT_COLOR = [0, 0, 0];

    private const MAX_REMOTE_IMAGE_BYTES = 10 * 1024 * 1024; // 10MB

    public function __construct(
        private ?Form $form = null
    ) {
    }

    /**
     * Render zone content (text or image) into the PDF.
     */
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
        if ($this->isImageUrl($value)) {
            $this->renderImage($pdf, (string) $value, $x, $y, $width, $height);
        } else {
            $this->render($pdf, (string) $value, $x, $y, $width, $height, $zone, $pageWidth);
        }
    }

    /**
     * Render image into the zone.
     */
    private function renderImage(
        Fpdi $pdf,
        string $imageUrl,
        float $x,
        float $y,
        float $width,
        float $height
    ): void {
        try {
            $imageContent = $this->getImageContent($imageUrl);

            if ($imageContent === null) {
                return;
            }

            // Write content to a temp file without extension first, then detect type
            $tempImage = tempnam(sys_get_temp_dir(), 'pdf_img_');
            file_put_contents($tempImage, $imageContent);

            $imageInfo = @getimagesize($tempImage);
            if ($imageInfo === false) {
                @unlink($tempImage);
                return;
            }

            // Map MIME type to extension FPDF understands
            $mimeToExt = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'png', // convert webp below
            ];
            $ext = $mimeToExt[$imageInfo['mime'] ?? ''] ?? null;
            if (!$ext) {
                @unlink($tempImage);
                return;
            }

            $typedTemp = $tempImage . '.' . $ext;
            rename($tempImage, $typedTemp);
            $tempImage = $typedTemp;

            $pdf->Image($tempImage, $x, $y, $width, $height);

            @unlink($tempImage);
        } catch (\Exception $e) {
            Log::debug('PDF image addition failed', [
                'form_id' => $this->form?->id,
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getImageContent(string $imageValue): ?string
    {
        if (filter_var($imageValue, FILTER_VALIDATE_URL)) {
            return $this->fetchExternalImage($imageValue);
        }

        if ($this->form) {
            try {
                $diskName = config('filesystems.default');
                $disk = Storage::disk($diskName);
                $storagePath = FileUploadPathService::getFileUploadPath($this->form->id, $imageValue);

                if (in_array($diskName, ['s3', 'do'])) {
                    /** @var \Illuminate\Filesystem\AwsS3V3Adapter $disk */
                    $tempUrl = $disk->temporaryUrl($storagePath, now()->addMinutes(5));
                    $content = @file_get_contents($tempUrl);
                    return $content !== false ? $content : null;
                }

                if ($disk->exists($storagePath)) {
                    return $disk->get($storagePath);
                }
            } catch (\Exception $e) {
                Log::debug('PDF storage image fetch failed', [
                    'form_id' => $this->form->id,
                    'path' => $imageValue,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function fetchExternalImage(string $url): ?string
    {
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? '';
        if (!in_array(strtolower($scheme), ['http', 'https'], true)) {
            Log::debug('PDF image fetch blocked: invalid scheme', ['url' => $url]);
            return null;
        }

        $host = $parsed['host'] ?? '';
        if (empty($host)) {
            return null;
        }

        $ip = gethostbyname($host);
        if ($ip === $host) {
            Log::debug('PDF image fetch blocked: DNS resolution failed', ['host' => $host]);
            return null;
        }

        if ($this->isPrivateIp($ip)) {
            Log::debug('PDF image fetch blocked: private/internal IP', ['host' => $host, 'ip' => $ip]);
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'follow_location' => 0,
                'max_redirects' => 0,
                'ignore_errors' => false,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            return null;
        }

        $meta = stream_get_meta_data($stream);
        if (!$this->isAllowedRemoteImageContentType($meta['wrapper_data'] ?? [])) {
            fclose($stream);
            Log::debug('PDF image fetch blocked: invalid content type', ['url' => $url]);
            return null;
        }

        $content = '';
        $bytesRead = 0;
        while (!feof($stream)) {
            $chunk = fread($stream, 8192);
            if ($chunk === false) {
                fclose($stream);
                return null;
            }
            $bytesRead += strlen($chunk);
            if ($bytesRead > self::MAX_REMOTE_IMAGE_BYTES) {
                fclose($stream);
                Log::debug('PDF image fetch blocked: content too large', ['url' => $url, 'bytes' => $bytesRead]);
                return null;
            }
            $content .= $chunk;
        }

        fclose($stream);
        return $content !== '' ? $content : null;
    }

    private function isAllowedRemoteImageContentType(array $headers): bool
    {
        foreach ($headers as $header) {
            if (!is_string($header)) {
                continue;
            }
            if (!str_starts_with(strtolower($header), 'content-type:')) {
                continue;
            }
            $contentType = trim(strtolower(substr($header, strlen('content-type:'))));
            return str_starts_with($contentType, 'image/')
                || str_starts_with($contentType, 'application/octet-stream');
        }
        return true;
    }

    private function isImageUrl(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
            $parts = parse_url($value);
            $path = $parts['path'] ?? '';

            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $path)) {
                return true;
            }

            if (str_contains($path, '/assets/')) {
                return true;
            }

            $query = $parts['query'] ?? '';
            parse_str($query, $queryParams);
            $format = strtolower((string) ($queryParams['fm'] ?? $queryParams['format'] ?? ''));
            if (in_array($format, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                return true;
            }

            return false;
        }

        return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $value);
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * Render HTML rich text into the zone.
     * Text is clipped to stay within the zone boundaries.
     */
    public function render(
        Fpdi $pdf,
        string $text,
        float $x,
        float $y,
        float $width,
        float $height,
        array $zone,
        float $pageWidth
    ): void {
        $baseFontSize = (int) ($zone['font_size'] ?? self::DEFAULT_FONT_SIZE);
        $baseColor = $this->parseColor($zone['font_color'] ?? null);
        $lineHeight = $baseFontSize * 0.4;
        $zoneBottom = $y + $height;

        // Save and set margins so text wraps within zone width
        $ref = new \ReflectionClass($pdf);
        $lProp = $ref->getProperty('lMargin');
        $rProp = $ref->getProperty('rMargin');
        $lProp->setAccessible(true);
        $rProp->setAccessible(true);
        $savedLeftMargin = $lProp->getValue($pdf);
        $savedRightMargin = $rProp->getValue($pdf);

        $pdf->SetLeftMargin($x);
        $pdf->SetRightMargin($pageWidth - ($x + $width));
        $pdf->SetXY($x, $y);

        $segments = $this->parseHtmlToSegments($text, $baseFontSize, $baseColor);

        foreach ($segments as $segment) {
            if ($pdf->GetY() >= $zoneBottom) {
                break;
            }

            $style = ($segment['bold'] ? 'B' : '') . ($segment['italic'] ? 'I' : '') . ($segment['underline'] ? 'U' : '');
            $fontSize = $segment['fontSize'];
            $color = $segment['color'];

            $pdf->SetFont('Helvetica', $style, $fontSize);
            $pdf->SetTextColor(...$color);

            if ($segment['newline']) {
                if ($pdf->GetY() + $lineHeight > $zoneBottom) {
                    break;
                }
                $pdf->Ln($lineHeight);
                $pdf->SetX($x);
            }

            if ($segment['text'] !== '') {
                $this->writeClipped($pdf, $segment['text'], $lineHeight, $width, $x, $zoneBottom);
            }
        }

        // Restore margins
        $pdf->SetLeftMargin($savedLeftMargin);
        $pdf->SetRightMargin($savedRightMargin);
    }

    /**
     * Write text line-by-line, stopping when we exceed the zone bottom.
     */
    private function writeClipped(Fpdi $pdf, string $text, float $lineHeight, float $width, float $x, float $zoneBottom): void
    {
        $lines = $this->wrapTextToLines($pdf, $text, $width);
        foreach ($lines as $line) {
            if ($pdf->GetY() + $lineHeight > $zoneBottom) {
                break;
            }
            $pdf->Cell($width, $lineHeight, $line, 0, 2, '', false);
            $pdf->SetX($x);
        }
    }

    /**
     * Wrap text to fit within the given width, splitting on newlines and word boundaries.
     */
    private function wrapTextToLines(Fpdi $pdf, string $text, float $width): array
    {
        $text = str_replace("\r", '', $text);
        $lines = [];
        $paragraphs = explode("\n", $text);
        // FPDF Cell uses (w - 2*cMargin) for text; cMargin is typically ~1mm
        $usableWidth = max(1, $width - 2);

        foreach ($paragraphs as $para) {
            $words = explode(' ', $para);
            $currentLine = '';
            foreach ($words as $word) {
                $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
                if ($pdf->GetStringWidth($testLine) <= $usableWidth) {
                    $currentLine = $testLine;
                } else {
                    if ($currentLine !== '') {
                        $lines[] = $currentLine;
                    }
                    $currentLine = $word;
                }
            }
            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }
        }

        return $lines;
    }

    private function parseHtmlToSegments(string $html, int $baseFontSize, array $baseColor): array
    {
        $segments = [];

        $wrapped = '<div>' . $html . '</div>';
        $doc = new \DOMDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8">' . $wrapped,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_use_internal_errors($internalErrors);

        $root = $doc->getElementsByTagName('div')->item(0)
            ?? $doc->getElementsByTagName('body')->item(0)
            ?? $doc->documentElement;

        if ($root) {
            $this->extractTextSegments($root, $segments, $baseFontSize, $baseColor, false, false, false, $baseFontSize, $baseColor);
        }

        if (empty($segments) && trim(strip_tags($html)) !== '') {
            $segments[] = [
                'text' => trim(strip_tags($html)),
                'bold' => false,
                'italic' => false,
                'underline' => false,
                'fontSize' => $baseFontSize,
                'color' => $baseColor,
                'newline' => false,
            ];
        }

        return $segments;
    }

    private function extractTextSegments(
        \DOMNode $node,
        array &$segments,
        int $baseFontSize,
        array $baseColor,
        bool $bold,
        bool $italic,
        bool $underline,
        int $fontSize,
        array $color
    ): void {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = $node->nodeValue;
            if ($text !== '') {
                $segments[] = [
                    'text' => $text,
                    'bold' => $bold,
                    'italic' => $italic,
                    'underline' => $underline,
                    'fontSize' => $fontSize,
                    'color' => $color,
                    'newline' => false,
                ];
            }
            return;
        }

        $name = strtolower($node->nodeName);

        $isBold = $bold || in_array($name, ['strong', 'b']);
        $isItalic = $italic || in_array($name, ['em', 'i']);
        $isUnderline = $underline || $name === 'u';

        $segmentFontSize = $fontSize;
        $segmentColor = $color;

        if ($name === 'h1') {
            $segmentFontSize = (int) round($baseFontSize * 2);
        } elseif ($name === 'h2') {
            $segmentFontSize = (int) round($baseFontSize * 1.5);
        }

        if ($node instanceof \DOMElement) {
            if ($node->hasAttribute('style')) {
                $parsed = $this->parseInlineColor($node->getAttribute('style'));
                if ($parsed !== null) {
                    $segmentColor = $parsed;
                }
            }
            if ($node->hasAttribute('class') && preg_match('/ql-color-(#[0-9A-Fa-f]{6}|[0-9A-Fa-f]{6})/', $node->getAttribute('class'), $cm)) {
                $hex = $cm[1];
                if (!str_starts_with($hex, '#')) {
                    $hex = '#' . $hex;
                }
                $segmentColor = $this->parseColor($hex);
            }
        }

        $blockElements = ['p', 'div', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        if (in_array($name, $blockElements) && !empty($segments)) {
            $segments[] = [
                'text' => '',
                'bold' => false,
                'italic' => false,
                'underline' => false,
                'fontSize' => $baseFontSize,
                'color' => $baseColor,
                'newline' => true,
            ];
        }

        if ($name === 'br') {
            $segments[] = [
                'text' => '',
                'bold' => false,
                'italic' => false,
                'underline' => false,
                'fontSize' => $baseFontSize,
                'color' => $baseColor,
                'newline' => true,
            ];
            return;
        }

        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->extractTextSegments($child, $segments, $baseFontSize, $baseColor, $isBold, $isItalic, $isUnderline, $segmentFontSize, $segmentColor);
            }
        }
    }

    private function parseInlineColor(string $style): ?array
    {
        if (preg_match('/color:\s*(#[0-9A-Fa-f]{6}|rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)|rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*[\d.]+\s*\))/', $style, $m)) {
            if (isset($m[1]) && str_starts_with($m[1], '#')) {
                return $this->parseColor($m[1]);
            }
            if (isset($m[2], $m[3], $m[4])) {
                return [(int) $m[2], (int) $m[3], (int) $m[4]];
            }
            if (isset($m[5], $m[6], $m[7])) {
                return [(int) $m[5], (int) $m[6], (int) $m[7]];
            }
        }
        return null;
    }

    private function parseColor(?string $color): array
    {
        if (empty($color)) {
            return self::DEFAULT_FONT_COLOR;
        }

        if (str_starts_with($color, '#')) {
            $hex = ltrim($color, '#');
            if (strlen($hex) === 6) {
                return [
                    hexdec(substr($hex, 0, 2)),
                    hexdec(substr($hex, 2, 2)),
                    hexdec(substr($hex, 4, 2)),
                ];
            }
        }

        return self::DEFAULT_FONT_COLOR;
    }
}
