<?php

namespace App\Service\Pdf;

class PdfColorParser
{
    private const DEFAULT_FONT_COLOR = [0, 0, 0];

    public function parseColor(?string $color): array
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

    public function parseInlineColor(string $style): ?array
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
}
