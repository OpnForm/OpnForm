<?php

namespace App\Integrations\Handlers;

use App\Models\Forms\Form;

class PdfIntegration extends AbstractIntegrationHandler
{
    public static function getValidationRules(?Form $form): array
    {
        return [
            'template_id' => 'required|exists:pdf_templates,id',
            'zone_mappings' => 'required|array',
            'zone_mappings.*.id' => 'required|string',
            'zone_mappings.*.page' => 'required|integer|min:1',
            'zone_mappings.*.x' => 'required|numeric|min:0|max:100',
            'zone_mappings.*.y' => 'required|numeric|min:0|max:100',
            'zone_mappings.*.width' => 'required|numeric|min:0|max:100',
            'zone_mappings.*.height' => 'required|numeric|min:0|max:100',
            'zone_mappings.*.field_id' => 'required|string',
            'zone_mappings.*.font_size' => 'nullable|integer|min:6|max:72',
            'zone_mappings.*.font_color' => 'nullable|string',
            'filename_pattern' => 'required|string|max:255',
        ];
    }

    public static function getValidationAttributes(): array
    {
        return [
            'template_id' => 'PDF Template',
            'zone_mappings' => 'Zone Mappings',
            'filename_pattern' => 'Filename Pattern',
        ];
    }

    protected function shouldRun(): bool
    {
        return $this->form->is_pro && parent::shouldRun();
    }

    /**
     * PDF integration has no actions to process on submission.
     * PDFs are generated on-demand when:
     * - User downloads from results table
     * - User downloads from success page (if enabled in form settings)
     * - Email integration attaches PDF (if enabled in email settings)
     *
     * This handler exists only for validation and configuration storage.
     */
    public function handle(): void
    {
        if (!$this->shouldRun()) {
            return;
        }

        // Nothing to do - PDF generation happens elsewhere on-demand
    }
}
