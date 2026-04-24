<?php

namespace App\Service\Pdf;

use setasign\Fpdi\Fpdi;

class PdfRichTextRenderer
{
    private const DEFAULT_FONT_SIZE = 12;

    private readonly PdfColorParser $colorParser;

    public function __construct(?PdfColorParser $colorParser = null)
    {
        $this->colorParser = $colorParser ?? new PdfColorParser();
    }

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
        $baseColor = $this->colorParser->parseColor($zone['font_color'] ?? null);
        $lineHeight = $baseFontSize * 0.4;
        $zoneBottom = $y + $height;

        // Set margins so text wraps inside the zone width.
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

        $pdf->SetLeftMargin($savedLeftMargin);
        $pdf->SetRightMargin($savedRightMargin);
    }

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

    private function wrapTextToLines(Fpdi $pdf, string $text, float $width): array
    {
        $text = str_replace("\r", '', $text);
        $lines = [];
        $paragraphs = explode("\n", $text);
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

        $isBold = $bold || in_array($name, ['strong', 'b'], true);
        $isItalic = $italic || in_array($name, ['em', 'i'], true);
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
                $parsed = $this->colorParser->parseInlineColor($node->getAttribute('style'));
                if ($parsed !== null) {
                    $segmentColor = $parsed;
                }
            }
            if ($node->hasAttribute('class') && preg_match('/ql-color-(#[0-9A-Fa-f]{6}|[0-9A-Fa-f]{6})/', $node->getAttribute('class'), $cm)) {
                $hex = $cm[1];
                if (!str_starts_with($hex, '#')) {
                    $hex = '#' . $hex;
                }
                $segmentColor = $this->colorParser->parseColor($hex);
            }
        }

        $blockElements = ['p', 'div', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        if (in_array($name, $blockElements, true) && !empty($segments)) {
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
}
