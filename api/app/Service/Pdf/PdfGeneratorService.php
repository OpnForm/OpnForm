<?php

namespace App\Service\Pdf;

use App\Exceptions\PdfNotSupportedException;
use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\PdfTemplate;
use App\Service\Forms\FormSubmissionFormatter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;

class PdfGeneratorService
{
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
        $addBranding = !($template->remove_branding && $form->is_pro);

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

        $renderer = new PdfZoneRenderer($this->form);
        $renderer->renderContent($pdf, $value, $x, $y, $width, $height, $zone, $pageSize['width']);
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
}
