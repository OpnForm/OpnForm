<?php

namespace App\Service\Pdf;

use App\Exceptions\PdfNotSupportedException;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;

/**
 * Pango/HarfBuzz shapes Devanagari before Cairo embeds a subset of the Noto font.
 * Only trusted, escaped text segments are passed to Pango, never submitted HTML.
 */
class PdfDevanagariTextRenderer
{
    public static function isRequired(string $text): bool
    {
        return preg_match('/[\x{0900}-\x{097F}\x{A8E0}-\x{A8FF}]/u', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) === 1;
    }

    public function render(Fpdi $pdf, array $segments, float $x, float $y, float $width, float $height): void
    {
        $binary = config('opnform.pdf.pango_binary');
        if (! is_string($binary) || ! is_executable($binary)) {
            throw new PdfNotSupportedException('This PDF contains Marathi or Devanagari text, but the required text renderer is not enabled. Please contact support.');
        }
        if ($width <= 0 || $height <= 0 || ! is_finite($width) || ! is_finite($height)) {
            return;
        }

        $markup = $this->buildMarkup($segments);
        if (strlen($markup) > 65536) {
            throw new PdfNotSupportedException('This PDF text area is too long to render. Please shorten it.');
        }

        $directory = sys_get_temp_dir().'/opnform-pdf-'.bin2hex(random_bytes(16));
        if (! mkdir($directory, 0700)) {
            throw new PdfNotSupportedException('Unable to prepare this PDF. Please try again.');
        }

        try {
            $fontDirectory = resource_path('fonts/noto-devanagari');
            if (! is_readable($fontDirectory.'/NotoSansDevanagari-Regular.ttf')
                || ! is_readable($fontDirectory.'/NotoSans-Regular.ttf')) {
                throw new \RuntimeException('Required font is unavailable.');
            }
            $cacheDirectory = $directory.'/font-cache';
            mkdir($cacheDirectory, 0700);
            $fontConfig = '<?xml version="1.0"?><!DOCTYPE fontconfig SYSTEM "urn:fontconfig:fonts.dtd"><fontconfig>'
                .'<dir>'.htmlspecialchars($fontDirectory, ENT_XML1, 'UTF-8').'</dir>'
                .'<cachedir>'.htmlspecialchars($cacheDirectory, ENT_XML1, 'UTF-8').'</cachedir></fontconfig>';
            file_put_contents($directory.'/fonts.conf', $fontConfig);
            file_put_contents($directory.'/input.txt', $markup);

            $process = new Process([
                $binary, '--no-display', '--backend=cairo', '--dpi=72', '--margin=0',
                '--background=transparent', '--font=Noto Sans Devanagari 12', '--language=mr',
                '--markup', '--wrap=word-char',
                '--width='.max(1, (int) round($width * 72 / 25.4)),
                '--output='.$directory.'/text.pdf', $directory.'/input.txt',
            ], null, ['FONTCONFIG_FILE' => $directory.'/fonts.conf', 'PANGOCAIRO_BACKEND' => 'fc']);
            $process->setTimeout(10);
            $process->run();
            if (! $process->isSuccessful() || ! is_file($directory.'/text.pdf')) {
                throw new \RuntimeException('PDF text rendering failed.');
            }

            $pdf->setSourceFile($directory.'/text.pdf');
            $overlay = $pdf->importPage(1);
            // Crop the drawn area instead of ellipsizing in Cairo: mapping a
            // long omitted run to one ellipsis can create an invalid ToUnicode
            // CMap. Keep the shaped vector content at its natural aspect ratio.
            $scale = 72 / 25.4;
            $pdf->_out(sprintf(
                'q %.4F %.4F %.4F %.4F re W n',
                $x * $scale,
                ($pdf->GetPageHeight() - $y - $height) * $scale,
                $width * $scale,
                $height * $scale
            ));
            try {
                $pdf->useTemplate($overlay, $x, $y, $width);
            } finally {
                $pdf->_out('Q');
            }
        } catch (\Throwable) {
            // Never include subprocess output: it can contain customer text.
            throw new PdfNotSupportedException('Unable to render the Marathi or Devanagari text in this PDF. Please contact support.');
        } finally {
            $this->removeTemporaryDirectory($directory);
        }
    }

    private function buildMarkup(array $segments): string
    {
        $markup = '';
        foreach ($segments as $segment) {
            if ($segment['newline']) {
                $markup .= "\n";
            }
            $color = sprintf('#%02x%02x%02x', ...$segment['color']);
            $size = max(1, min(144, (int) $segment['fontSize'])) * 1024;
            $markup .= '<span foreground="'.$color.'" size="'.$size.'"'
                .($segment['bold'] ? ' weight="bold"' : '')
                .($segment['italic'] ? ' style="italic"' : '')
                .($segment['underline'] ? ' underline="single"' : '')
                .'>'.htmlspecialchars($segment['text'], ENT_QUOTES | ENT_XML1, 'UTF-8').'</span>';
        }

        return $markup;
    }

    private function removeTemporaryDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($directory);
    }
}
