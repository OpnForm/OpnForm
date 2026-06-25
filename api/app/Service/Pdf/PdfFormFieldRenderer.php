<?php

namespace App\Service\Pdf;

use App\Models\Forms\Form;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class PdfFormFieldRenderer
{
    private const MARGIN_X = 5;
    private const MARGIN_TOP = 4;
    private const MARGIN_BOTTOM = 4;
    private const FIELD_LABEL_HEIGHT = 2.5;
    private const FIELD_VALUE_HEIGHT = 3;
    private const FIELD_GAP = 0.5;
    private const FIELD_SPACING = 1.5;
    private const DEFAULT_FONT_SIZE = 12;
    private const TITLE_ZONE_HEIGHT = 7;
    private const TITLE_BASE_FONT_SIZE = 12;
    private const REQUIRED_MARK_HTML = '<strong style="color: #EF4444">*</strong>';

    private float $cursorY;
    private int $currentPageNum = 0;
    private array $pages = [];
    private array $zones = [];
    private string $currentPageId;

    /**
     * Build zone mappings and page manifest for a from-scratch PDF template.
     *
     * @return array{zones: array, pages: array, page_count: int, pdf_content: string}
     */
    public function generate(Form $form): array
    {
        $this->pages = [];
        $this->zones = [];
        $this->addPage();

        // Form title as h1 static text zone
        $title = htmlspecialchars($form->title ?? 'Untitled Form', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $this->addStaticTextZone("<h1>{$title}</h1>", self::TITLE_ZONE_HEIGHT, fontSize: self::TITLE_BASE_FONT_SIZE);
        $this->cursorY += 1;

        $properties = collect($form->properties ?? [])
            ->filter(fn ($field) => !str_starts_with($field['type'] ?? '', 'nf-'))
            ->values();

        foreach ($properties as $field) {
            $estimatedHeight = $this->estimateFieldHeight($field);
            if ($this->cursorY + $estimatedHeight > 100 - self::MARGIN_BOTTOM) {
                $this->addPage();
            }

            $this->addFieldZones($field);
        }

        return [
            'zones' => $this->zones,
            'pages' => $this->pages,
            'page_count' => count($this->pages),
            'pdf_content' => $this->generateBlankPdf(count($this->pages)),
        ];
    }

    private function addPage(): void
    {
        $this->currentPageNum++;
        $this->currentPageId = (string) Str::uuid();
        $this->pages[] = [
            'id' => $this->currentPageId,
            'type' => 'source',
            'source_page' => $this->currentPageNum,
        ];
        $this->cursorY = self::MARGIN_TOP;
    }

    private function addFieldZones(array $field): void
    {
        $type = $field['type'] ?? 'text';
        $name = $field['name'] ?? 'Unnamed Field';
        $fieldId = $field['id'] ?? null;
        $required = !empty($field['required']);

        // Label zone
        $escapedName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $label = $required ? $escapedName . ' ' . self::REQUIRED_MARK_HTML : $escapedName;
        $this->addStaticTextZone($label, self::FIELD_LABEL_HEIGHT, fontSize: 10, fontColor: '#374151');

        // Small gap between label and value
        $this->cursorY += self::FIELD_GAP;

        if (!$fieldId) {
            return;
        }

        // Value zone
        $zoneHeight = $this->getFieldZoneHeight($type, $field);
        $this->addFieldValueZone($fieldId, $zoneHeight);

        // Spacing before next field
        $this->cursorY += self::FIELD_SPACING;
    }

    private function addStaticTextZone(string $text, float $height, int $fontSize = self::DEFAULT_FONT_SIZE, string $fontColor = '#000000'): void
    {
        $this->zones[] = [
            'id' => (string) Str::uuid(),
            'page' => $this->currentPageNum,
            'page_id' => $this->currentPageId,
            'x' => self::MARGIN_X,
            'y' => $this->cursorY,
            'width' => 100 - (2 * self::MARGIN_X),
            'height' => $height,
            'static_text' => $text,
            'font_size' => $fontSize,
            'font_color' => $fontColor,
        ];
        $this->cursorY += $height;
    }

    private function addFieldValueZone(string $fieldId, float $height): void
    {
        $this->zones[] = [
            'id' => (string) Str::uuid(),
            'page' => $this->currentPageNum,
            'page_id' => $this->currentPageId,
            'x' => self::MARGIN_X,
            'y' => $this->cursorY,
            'width' => 100 - (2 * self::MARGIN_X),
            'height' => $height,
            'field_id' => $fieldId,
            'font_size' => self::DEFAULT_FONT_SIZE,
            'font_color' => '#111827',
        ];
        $this->cursorY += $height;
    }

    private function getFieldZoneHeight(string $type, array $field): float
    {
        return match ($type) {
            'text' => !empty($field['multi_lines']) ? 10 : self::FIELD_VALUE_HEIGHT,
            'rich_text' => 10,
            'signature' => 10,
            'files' => 8,
            'matrix' => min(5 + count($field['rows'] ?? []) * 3, 20),
            'multi_select' => min(4 + count($this->getSelectOptionCount($field)) * 2, 14),
            default => self::FIELD_VALUE_HEIGHT,
        };
    }

    private function estimateFieldHeight(array $field): float
    {
        $type = $field['type'] ?? 'text';

        return self::FIELD_LABEL_HEIGHT + self::FIELD_GAP + $this->getFieldZoneHeight($type, $field) + self::FIELD_SPACING;
    }

    private function getSelectOptionCount(array $field): array
    {
        $options = $field['multi_select'] ?? $field['select'] ?? [];
        if (!is_array($options)) {
            return [];
        }

        if (isset($options['options'])) {
            $options = $options['options'];
        }

        return is_array($options) ? $options : [];
    }

    private function generateBlankPdf(int $pageCount): string
    {
        $pdf = new Fpdi();
        for ($i = 0; $i < $pageCount; $i++) {
            $pdf->AddPage();
        }

        return $pdf->Output('S');
    }
}
