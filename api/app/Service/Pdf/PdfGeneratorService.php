<?php

namespace App\Service\Pdf;

use App\Exceptions\PdfNotSupportedException;
use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\PdfTemplate;
use App\Service\Forms\FormSubmissionFormatter;
use App\Service\Storage\FileUploadPathService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;

class PdfGeneratorService
{
    private const DEFAULT_FONT_SIZE = 12;
    private const DEFAULT_FONT_COLOR = [0, 0, 0]; // Black

    // Use a consistent temp folder for lifecycle management
    private const TEMP_FOLDER = 'tmp/pdf-output';

    private ?Form $form = null;

    /**
     * Generate a PDF for a submission directly from a template.
     */
    public function generateFromTemplate(
        Form $form,
        FormSubmission $submission,
        PdfTemplate $template
    ): string {
        $this->form = $form;

        // Zone mappings are now stored on the template
        $zoneMappings = $template->zone_mappings ?? [];

        // Get submission data formatted for display
        $submissionData = $this->getFormattedSubmissionData($form, $submission);

        // Check if branding should be added
        $addBranding = !$template->remove_branding;

        // Generate the PDF
        $pdfContent = $this->generatePdfContent($template, $zoneMappings, $submissionData, $addBranding);

        // Store in consistent temp folder for lifecycle cleanup
        $tempPath = self::TEMP_FOLDER . '/' . Str::uuid() . '.pdf';
        Storage::put($tempPath, $pdfContent);

        return $tempPath;
    }

    /**
     * Generate PDF content using FPDI/FPDF.
     *
     * @throws PdfNotSupportedException
     */
    private function generatePdfContent(
        PdfTemplate $template,
        array $zoneMappings,
        array $submissionData,
        bool $addBranding = false
    ): string {
        // Get template file content
        $templatePath = $template->file_path;
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_template_');

        // Copy template to temp file
        file_put_contents($tempFile, Storage::get($templatePath));

        try {
            // Create FPDI instance
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($tempFile);
        } catch (CrossReferenceException $e) {
            @unlink($tempFile);
            throw new PdfNotSupportedException();
        }

        // Group zones by page
        $zonesByPage = [];
        foreach ($zoneMappings as $zone) {
            $page = $zone['page'] ?? 1;
            if (!isset($zonesByPage[$page])) {
                $zonesByPage[$page] = [];
            }
            $zonesByPage[$page][] = $zone;
        }

        // Process each page
        for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
            // Import the page
            $templateId = $pdf->importPage($pageNum);
            $size = $pdf->getTemplateSize($templateId);

            // Add a page with the same size as the template
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);

            // Use the imported page
            $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);

            // Add zones for this page
            if (isset($zonesByPage[$pageNum])) {
                foreach ($zonesByPage[$pageNum] as $zone) {
                    $this->addZoneContent($pdf, $zone, $submissionData, $size);
                }
            }

            // Add branding footer on every page if required
            if ($addBranding) {
                $this->addBrandingFooter($pdf, $size);
            }
        }

        // Clean up temp file
        @unlink($tempFile);

        // Return PDF content
        return $pdf->Output('S');
    }

    /**
     * Add OpnForm branding footer: "PDF generated with [LOGO] OpnForm".
     */
    private function addBrandingFooter(Fpdi $pdf, array $pageSize): void
    {
        $width = $pageSize['width'];
        $height = $pageSize['height'];
        $marginBottom = 5;
        $logoHeight = 5;
        $logoWidth = 5;

        $pdf->SetFont('Helvetica', '', 12);
        $pdf->SetTextColor(128, 128, 128);

        $textBefore = 'PDF generated with ';
        $textAfter = ' OpnForm';
        $wBefore = $pdf->GetStringWidth($textBefore);
        $wAfter = $pdf->GetStringWidth($textAfter);

        $logoPath = resource_path('images/logo.png');
        $hasLogo = is_file($logoPath);

        $totalWidth = $wBefore + ($hasLogo ? $logoWidth : 0) + $wAfter;
        $startX = ($width - $totalWidth) / 2;
        $x = $startX;
        $y = $height - $marginBottom;

        $pdf->Text($x, $y, $textBefore);
        $x += $wBefore;

        if ($hasLogo) {
            $logoY = $y - $logoHeight;
            $pdf->Image($logoPath, $x, $logoY, $logoWidth, $logoHeight);
            $x += $logoWidth;
        }

        $pdf->Text($x, $y, $textAfter);

        // Make the whole branding line clickable
        $linkY = $y - $logoHeight;
        $linkH = $logoHeight;
        $pdf->Link($startX, $linkY, $totalWidth, $linkH, front_url());
    }

    /**
     * Add content to a zone on the PDF.
     * Supports both field mappings and static text.
     */
    private function addZoneContent(Fpdi $pdf, array $zone, array $submissionData, array $pageSize): void
    {
        // Check for static text first (hardcoded content)
        $staticText = $zone['static_text'] ?? null;
        if (!empty($staticText)) {
            $value = $staticText;
        } else {
            $fieldId = $zone['field_id'] ?? null;
            $value = $this->getFieldValue($fieldId, $submissionData);
        }

        if (empty($value)) {
            return;
        }

        // Convert percentage coordinates to absolute coordinates
        $x = ($zone['x'] / 100) * $pageSize['width'];
        $y = ($zone['y'] / 100) * $pageSize['height'];
        $width = ($zone['width'] / 100) * $pageSize['width'];
        $height = ($zone['height'] / 100) * $pageSize['height'];

        // Auto-detect: render as image if value is an image URL, otherwise as text
        if ($this->isImageUrl($value)) {
            $this->addImageToZone($pdf, $value, $x, $y, $width, $height);
        } else {
            $this->addTextToZone($pdf, (string) $value, $x, $y, $width, $height, $zone);
        }
    }

    /**
     * Add text to a zone.
     */
    private function addTextToZone(
        Fpdi $pdf,
        string $text,
        float $x,
        float $y,
        float $width,
        float $height,
        array $zone
    ): void {
        $fontSize = $zone['font_size'] ?? self::DEFAULT_FONT_SIZE;
        $fontColor = $this->parseColor($zone['font_color'] ?? null);

        $pdf->SetFont('Helvetica', '', $fontSize);
        $pdf->SetTextColor(...$fontColor);
        $pdf->SetXY($x, $y);

        // Use MultiCell for text that might wrap
        $pdf->MultiCell($width, $height / 3, $text, 0, 'L');
    }

    /**
     * Add image to a zone.
     * Handles both internal storage files and external URLs.
     */
    private function addImageToZone(
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

            // Create temp file with proper extension
            $extension = pathinfo($imageUrl, PATHINFO_EXTENSION) ?: 'png';
            $tempImage = tempnam(sys_get_temp_dir(), 'pdf_img_') . '.' . $extension;
            file_put_contents($tempImage, $imageContent);

            // Detect image type
            $imageInfo = @getimagesize($tempImage);
            if ($imageInfo === false) {
                @unlink($tempImage);
                return;
            }

            $pdf->Image($tempImage, $x, $y, $width, $height);

            @unlink($tempImage);
        } catch (\Exception $e) {
            Log::debug('PDF image addition failed', [
                'form_id' => $this->form->id,
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get image content from either storage or external URL.
     * External URLs are validated to prevent SSRF attacks.
     */
    private function getImageContent(string $imageValue): ?string
    {
        // Check if it's an external URL
        if (filter_var($imageValue, FILTER_VALIDATE_URL)) {
            return $this->fetchExternalImage($imageValue);
        }

        // It's a storage filename - read from storage using default disk
        if ($this->form) {
            try {
                $diskName = config('filesystems.default');
                $disk = Storage::disk($diskName);
                $storagePath = FileUploadPathService::getFileUploadPath($this->form->id, $imageValue);

                // For S3/cloud storage, get a temporary URL and download
                if (in_array($diskName, ['s3', 'do'])) {
                    /** @var \Illuminate\Filesystem\AwsS3V3Adapter $disk */
                    $tempUrl = $disk->temporaryUrl($storagePath, now()->addMinutes(5));
                    // Safe to fetch - this is our own S3 URL
                    $content = @file_get_contents($tempUrl);
                    return $content !== false ? $content : null;
                }

                // For local storage, read directly
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

    /**
     * Fetch external image with SSRF protection.
     * Validates URL scheme and blocks private/internal IP addresses.
     */
    private function fetchExternalImage(string $url): ?string
    {
        $parsed = parse_url($url);

        // Only allow http/https schemes
        $scheme = $parsed['scheme'] ?? '';
        if (!in_array(strtolower($scheme), ['http', 'https'], true)) {
            Log::debug('PDF image fetch blocked: invalid scheme', ['url' => $url]);
            return null;
        }

        $host = $parsed['host'] ?? '';
        if (empty($host)) {
            return null;
        }

        // Resolve hostname to IP
        $ip = gethostbyname($host);
        if ($ip === $host) {
            Log::debug('PDF image fetch blocked: DNS resolution failed', ['host' => $host]);
            return null;
        }

        // Block private/internal IP addresses to prevent SSRF
        if ($this->isPrivateIp($ip)) {
            Log::debug('PDF image fetch blocked: private/internal IP', ['host' => $host, 'ip' => $ip]);
            return null;
        }

        // Fetch with timeout using stream context
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'max_redirects' => 3,
                'ignore_errors' => false,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);
        return $content !== false ? $content : null;
    }

    /**
     * Get field value from submission data.
     */
    private function getFieldValue(string $fieldId, array $submissionData): mixed
    {
        // Check for direct field match
        if (isset($submissionData[$fieldId])) {
            return $submissionData[$fieldId];
        }

        // Check for special fields
        $specialFields = [
            'submission_id' => $submissionData['submission_id'] ?? null,
            'submission_date' => $submissionData['submission_date'] ?? null,
            'form_name' => $submissionData['form_name'] ?? null,
        ];

        return $specialFields[$fieldId] ?? null;
    }

    /**
     * Parse color string to RGB array.
     */
    private function parseColor(?string $color): array
    {
        if (empty($color)) {
            return self::DEFAULT_FONT_COLOR;
        }

        // Handle hex color
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

    /**
     * Check if value is an image (URL or storage filename).
     */
    private function isImageUrl(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Check if it's an image URL
        if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return preg_match('/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $value);
        }

        // Check if it's a storage filename with image extension
        return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $value);
    }

    /**
     * Get formatted submission data.
     * For file/signature fields, keeps raw filenames instead of URLs for direct storage access.
     */
    private function getFormattedSubmissionData(Form $form, FormSubmission $submission): array
    {
        $formatter = new FormSubmissionFormatter($form, $submission->data);
        $formatted = $formatter->outputStringsOnly()->getFieldsWithValue();
        $rawData = $submission->data;

        $data = [];
        foreach ($formatted as $field) {
            // For file/signature fields, use the raw filename instead of signed URL
            if (in_array($field['type'], ['files', 'signature']) && isset($rawData[$field['id']])) {
                $files = $rawData[$field['id']];
                // Get first file if it's an array (for single image in PDF zone)
                $data[$field['id']] = is_array($files) && !empty($files) ? $files[0] : $files;
            } else {
                $data[$field['id']] = $field['value'];
            }
        }

        // Add special fields
        $data['submission_id'] = $submission->id ?: 'preview';
        $data['submission_date'] = $submission->created_at ? $submission->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        $data['form_name'] = $form->title;

        return $data;
    }

    /**
     * Check if an IP address is private or reserved (internal network).
     * Used to prevent SSRF attacks by blocking requests to internal resources.
     */
    private function isPrivateIp(string $ip): bool
    {
        // FILTER_FLAG_NO_PRIV_RANGE: Fails for private IPv4 ranges (10.x, 172.16-31.x, 192.168.x)
        // FILTER_FLAG_NO_RES_RANGE: Fails for reserved ranges (0.0.0.0/8, 169.254.x, 127.x, etc.)
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
