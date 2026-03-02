<?php

namespace App\Service\Pdf;

use setasign\Fpdi\Fpdi;

class PdfImageRenderer
{
    /**
     * Render binary image content into a PDF zone.
     */
    public function render(
        Fpdi $pdf,
        string $imageContent,
        float $x,
        float $y,
        float $width,
        float $height
    ): void {
        $tempImage = tempnam(sys_get_temp_dir(), 'pdf_img_');
        if ($tempImage === false) {
            return;
        }

        try {
            file_put_contents($tempImage, $imageContent);
            $imageInfo = @getimagesize($tempImage);
            if ($imageInfo === false) {
                return;
            }

            $mimeToExt = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
            ];
            $ext = $mimeToExt[$imageInfo['mime'] ?? ''] ?? null;
            if (!$ext) {
                return;
            }

            $typedTemp = $tempImage . '.' . $ext;
            rename($tempImage, $typedTemp);
            $tempImage = $typedTemp;

            $pdf->Image($tempImage, $x, $y, $width, $height);
        } finally {
            @unlink($tempImage);
        }
    }
}
