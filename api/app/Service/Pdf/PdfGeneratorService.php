<?php

namespace App\Service\Pdf;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\Integration\FormIntegration;
use App\Models\PdfTemplate;
use App\Service\Forms\FormSubmissionFormatter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class PdfGeneratorService
{
    private const DEFAULT_FONT_SIZE = 12;
    private const DEFAULT_FONT_COLOR = [0, 0, 0]; // Black

    /**
     * Generate a PDF for a submission based on integration configuration.
     */
    public function generate(
        Form $form,
        FormSubmission $submission,
        FormIntegration $integration
    ): string {
        $data = $integration->data;
        $template = PdfTemplate::findOrFail($data->template_id);
        // Convert zone_mappings from object to array (since data is cast as 'object')
        $zoneMappings = json_decode(json_encode($data->zone_mappings ?? []), true);
        $filenamePattern = $data->filename_pattern ?? '{form_name}-{submission_id}.pdf';

        // Get submission data formatted for display
        $submissionData = $this->getFormattedSubmissionData($form, $submission);

        // Generate the PDF
        $pdfContent = $this->generatePdfContent($template, $zoneMappings, $submissionData);

        // Generate filename
        $filename = $this->generateFilename($filenamePattern, $form, $submission, $submissionData);

        // Store temporarily and return path
        $tempPath = 'pdf-generated/' . Str::uuid() . '.pdf';
        Storage::put($tempPath, $pdfContent);

        return $tempPath;
    }

    /**
     * Generate PDF content using FPDI/FPDF.
     */
    private function generatePdfContent(
        PdfTemplate $template,
        array $zoneMappings,
        array $submissionData
    ): string {
        // Get template file content
        $templatePath = $template->file_path;
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_template_');

        // Copy template to temp file
        file_put_contents($tempFile, Storage::get($templatePath));

        // Create FPDI instance
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($tempFile);

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
        }

        // Clean up temp file
        @unlink($tempFile);

        // Return PDF content
        return $pdf->Output('S');
    }

    /**
     * Add content to a zone on the PDF.
     */
    private function addZoneContent(Fpdi $pdf, array $zone, array $submissionData, array $pageSize): void
    {
        $fieldId = $zone['field_id'] ?? null;
        $value = $this->getFieldValue($fieldId, $submissionData);

        if (empty($value)) {
            return;
        }

        // Convert percentage coordinates to absolute coordinates
        $x = ($zone['x'] / 100) * $pageSize['width'];
        $y = ($zone['y'] / 100) * $pageSize['height'];
        $width = ($zone['width'] / 100) * $pageSize['width'];
        $height = ($zone['height'] / 100) * $pageSize['height'];

        $type = $zone['type'] ?? 'text';

        if ($type === 'image' && $this->isImageUrl($value)) {
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
            // Download image to temp file
            $imageContent = @file_get_contents($imageUrl);
            if ($imageContent === false) {
                return;
            }

            $tempImage = tempnam(sys_get_temp_dir(), 'pdf_image_');
            file_put_contents($tempImage, $imageContent);

            // Detect image type
            $imageInfo = @getimagesize($tempImage);
            if ($imageInfo === false) {
                @unlink($tempImage);
                return;
            }

            // Add image, maintaining aspect ratio within zone bounds
            $pdf->Image($tempImage, $x, $y, $width, $height);

            @unlink($tempImage);
        } catch (\Exception $e) {
            // Silently fail if image cannot be added
        }
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
     * Check if value is an image URL.
     */
    private function isImageUrl(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && preg_match('/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $value);
    }

    /**
     * Generate filename from pattern.
     */
    private function generateFilename(
        string $pattern,
        Form $form,
        FormSubmission $submission,
        array $submissionData
    ): string {
        $replacements = [
            '{form_name}' => Str::slug($form->title),
            '{submission_id}' => $submission->id,
            '{date}' => now()->format('Y-m-d'),
            '{timestamp}' => now()->timestamp,
        ];

        // Add field replacements
        foreach ($submissionData as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $replacements['{{' . $key . '}}'] = Str::slug((string) $value);
            }
        }

        $filename = str_replace(array_keys($replacements), array_values($replacements), $pattern);

        // Ensure .pdf extension
        if (!str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        // Sanitize filename
        return preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename);
    }

    /**
     * Get formatted submission data.
     */
    private function getFormattedSubmissionData(Form $form, FormSubmission $submission): array
    {
        $formatter = new FormSubmissionFormatter($form, $submission->data);
        $formatted = $formatter->outputStringsOnly()->getFieldsWithValue();

        $data = [];
        foreach ($formatted as $field) {
            $data[$field['id']] = $field['value'];
        }

        // Add special fields
        $data['submission_id'] = $submission->id;
        $data['submission_date'] = $submission->created_at->format('Y-m-d H:i:s');
        $data['form_name'] = $form->title;

        return $data;
    }
}
