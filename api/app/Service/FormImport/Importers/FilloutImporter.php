<?php

namespace App\Service\FormImport\Importers;

use App\Service\FormImport\FormImportException;

class FilloutImporter extends AbstractImporter
{
    private const FIELD_MAP = [
        'ShortAnswer' => 'text',
        'LongAnswer' => 'text',
        'EmailInput' => 'email',
        'PhoneNumber' => 'phone_number',
        'NumberInput' => 'number',
        'URLInput' => 'url',
        'DatePicker' => 'date',
        'DateRange' => 'date',
        'Dropdown' => 'select',
        'MultiSelect' => 'multi_select',
        'MultipleChoice' => 'multi_select',
        'Rating' => 'rating',
        'StarRating' => 'rating',
        'Slider' => 'slider',
        'FileUpload' => 'files',
        'Signature' => 'signature',
        'Text' => 'nf-text',
        'Checkbox' => 'checkbox',
        'Checkboxes' => 'multi_select',
        'Switch' => 'checkbox',
        'OpinionScale' => 'scale',
        'Matrix' => 'matrix',
        'PageBreak' => 'nf-page-break',
    ];

    private const SKIP_TYPES = ['Button', 'ThankYou', 'thank_you'];

    public function import(array $importData): array
    {
        $html = $this->fetchHtml($importData['url']);
        $data = $this->extractNextData($html);

        return $this->parseFormData($data);
    }

    public function allowedDomains(): array
    {
        return ['fillout.com', '*.fillout.com'];
    }

    private function parseFormData(array $data): array
    {
        $pageProps = $data['props']['pageProps'] ?? null;

        if (!$pageProps) {
            throw new FormImportException('Could not find form data in the page structure.');
        }

        $title = $this->sanitizeText(
            $pageProps['flow']['name'] ?? 'Imported Fillout Form',
            60
        );

        $steps = $pageProps['flowSnapshot']['template']['steps'] ?? [];
        $properties = $this->mapSteps($steps);

        return [
            'title' => $title,
            'properties' => $properties,
        ];
    }

    private function mapSteps(array $steps): array
    {
        $properties = [];

        foreach ($steps as $step) {
            $widgets = $step['template']['widgets'] ?? [];
            foreach ($widgets as $widget) {
                $mapped = $this->mapWidget($widget);
                if ($mapped) {
                    $properties[] = $mapped;
                }
            }
        }

        return $properties;
    }

    private function mapWidget(array $widget): ?array
    {
        $filloutType = $widget['type'] ?? null;

        if (!$filloutType || in_array($filloutType, self::SKIP_TYPES)) {
            return null;
        }

        $opnType = self::FIELD_MAP[$filloutType] ?? 'text';
        $template = $widget['template'] ?? [];

        $label = $this->extractLabel($template);
        $required = $this->extractRequired($template);

        $property = [
            'id' => $this->generateFieldId(),
            'name' => $this->sanitizeText($label ?: ($widget['name'] ?? 'Untitled'), 255),
            'type' => $opnType,
            'required' => $required,
            'hidden' => false,
        ];

        $placeholder = $this->extractPlaceholder($template);
        if ($placeholder) {
            $property['placeholder'] = $placeholder;
        }

        switch ($filloutType) {
            case 'Switch':
                $property['use_toggle_switch'] = true;
                break;

            case 'OpinionScale':
                $property['scale_min_value'] = $template['minValue'] ?? 1;
                $property['scale_max_value'] = $template['maxValue'] ?? 5;
                $property['scale_step_value'] = 1;
                break;

            case 'DateRange':
                $property['date_range'] = true;
                break;

            case 'Matrix':
                $property = $this->addMatrixOptions($property, $template);
                break;

            case 'LongAnswer':
                $property['multi_lines'] = true;
                break;

            case 'MultipleChoice':
                $options = $this->extractOptions($template);
                $property = $this->addSelectOptions($property, $options);
                if (count($options) <= 5) {
                    $property['without_dropdown'] = true;
                }
                break;

            case 'Dropdown':
                $options = $this->extractOptions($template);
                $property = $this->addSelectOptions($property, $options);
                break;

            case 'MultiSelect':
            case 'Checkboxes':
                $options = $this->extractOptions($template);
                $property = $this->addSelectOptions($property, $options);
                if (count($options) <= 5) {
                    $property['without_dropdown'] = true;
                }
                break;

            case 'Rating':
                $property['rating_max_value'] = $template['maxRating']['logic'] ?? 5;
                break;

            case 'Slider':
                $property['slider_min_value'] = $template['minValue']['logic'] ?? 0;
                $property['slider_max_value'] = $template['maxValue']['logic'] ?? 100;
                $property['slider_step_value'] = $template['step']['logic'] ?? 1;
                break;

            case 'Text':
                $property['type'] = 'nf-text';
                $property['content'] = '<p>' . e($this->sanitizeText($label, 2000)) . '</p>';
                unset($property['required'], $property['hidden']);
                break;
        }

        return $property;
    }

    private function extractLabel(array $template): string
    {
        $label = $template['label'] ?? null;

        if (is_array($label)) {
            return $label['logic']['value'] ?? $label['logic'] ?? '';
        }

        return $label ?? '';
    }

    private function extractRequired(array $template): bool
    {
        $required = $template['required'] ?? null;

        if (is_array($required)) {
            return (bool) ($required['logic'] ?? false);
        }

        return (bool) $required;
    }

    private function extractPlaceholder(array $template): string
    {
        $placeholder = $template['placeholder'] ?? null;

        if (is_array($placeholder)) {
            $val = $placeholder['logic']['value'] ?? $placeholder['logic'] ?? '';

            return is_string($val) ? $val : '';
        }

        return is_string($placeholder) ? $placeholder : '';
    }

    private function extractOptions(array $template): array
    {
        $optionsData = $template['options']['staticOptions']
            ?? $template['options'] ?? [];

        return array_filter(array_map(function ($opt) {
            if (isset($opt['label'])) {
                $label = $opt['label'];
                if (is_array($label)) {
                    return $this->sanitizeText($label['logic']['value'] ?? $label['logic'] ?? '');
                }

                return $this->sanitizeText($label);
            }

            return $this->sanitizeText($opt['value'] ?? $opt['name'] ?? '');
        }, $optionsData));
    }

    private function addSelectOptions(array $property, array $options): array
    {
        if (!empty($options)) {
            $property[$property['type']]['options'] = array_map(
                fn($label) => ['id' => $this->generateFieldId(), 'name' => $label],
                array_values($options)
            );
        }

        return $property;
    }

    private function addMatrixOptions(array $property, array $template): array
    {
        $property['rows'] = array_map(function ($row) {
            return $this->sanitizeText($row['label']['logic']['value']  ?? $row['name'] ?? 'Row');
        }, $template['rows']);

        $property['columns'] = array_map(function ($column) {
            return $this->sanitizeText($column['label']['logic']['value'] ?? $column['name'] ?? 'Column');
        }, $template['columns']);

        return $property;
    }
}
