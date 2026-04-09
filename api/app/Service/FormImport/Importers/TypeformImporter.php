<?php

namespace App\Service\FormImport\Importers;

use App\Service\FormImport\FormImportException;
use Illuminate\Support\Facades\Http;

class TypeformImporter extends AbstractImporter
{
    private const API_BASE = 'https://api.typeform.com/forms/';

    private const FIELD_MAP = [
        'short_text' => 'text',
        'long_text' => 'text',
        'email' => 'email',
        'phone_number' => 'phone_number',
        'number' => 'number',
        'url' => 'url',
        'website' => 'url',
        'date' => 'date',
        'dropdown' => 'select',
        'rating' => 'rating',
        'opinion_scale' => 'scale',
        'nps' => 'scale',
        'yes_no' => 'checkbox',
        'legal' => 'checkbox',
        'checkbox' => 'checkbox',
        'file_upload' => 'files',
        'signature' => 'signature',
        'multiple_choice' => 'select',
        'picture_choice' => 'select',
        'ranking' => 'select',
        'statement' => 'nf-text',
    ];

    private const COMPOSITE_TYPES = ['contact_info', 'address', 'group', 'inline_group'];

    public function import(array $importData): array
    {
        $formId = $this->extractFormId($importData['url']);
        $formData = $this->fetchFormData($formId);

        $title = $this->sanitizeText($formData['title'] ?? 'Imported Typeform', 60);
        $properties = $this->mapFields($formData['fields'] ?? []);

        return [
            'title' => $title,
            'properties' => $properties,
            'presentation_style' => 'focused',
            'size' => 'lg',
            'settings' => [
                'navigation_arrows' => true,
            ],
        ];
    }

    public function allowedDomains(): array
    {
        return ['*.typeform.com'];
    }

    protected function extractFormId(string $url): string
    {
        if (preg_match('#typeform\.com/to/([a-zA-Z0-9]+)#', $url, $matches)) {
            return $matches[1];
        }

        throw new FormImportException(
            'Could not extract form ID from Typeform URL. Expected format: https://yourname.typeform.com/to/FORM_ID'
        );
    }

    private function fetchFormData(string $formId): array
    {
        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; OpnFormImporter/1.0)',
            ])
            ->get(self::API_BASE . $formId);

        if ($response->status() === 404 || $response->status() === 403) {
            throw new FormImportException(
                'Form not found or not publicly accessible. Make sure the Typeform is published and public.'
            );
        }

        if (!$response->successful()) {
            throw new FormImportException(
                'Failed to fetch Typeform data. HTTP status: ' . $response->status()
            );
        }

        return $response->json();
    }

    private function mapFields(array $fields): array
    {
        $properties = [];

        foreach ($fields as $field) {
            $type = $field['type'] ?? 'short_text';

            if (in_array($type, self::COMPOSITE_TYPES)) {
                array_push($properties, ...$this->flattenCompositeField($field));
            } else {
                $mapped = $this->mapSingleField($field);
                if ($mapped) {
                    $properties[] = $mapped;
                }
            }
        }

        return $properties;
    }

    /**
     * Flatten composite Typeform types (contact_info, address, group, inline_group)
     * into individual OpnForm fields.
     */
    private function flattenCompositeField(array $field): array
    {
        $type = $field['type'];
        $subFields = $field['properties']['fields'] ?? [];
        $properties = [];

        foreach ($subFields as $subField) {
            $subType = $subField['type'] ?? 'short_text';

            if (in_array($subType, self::COMPOSITE_TYPES)) {
                array_push($properties, ...$this->flattenCompositeField($subField));
            } else {
                $mapped = $this->mapSingleField($subField);
                if ($mapped) {
                    $properties[] = $mapped;
                }
            }
        }

        return $properties;
    }

    private function mapSingleField(array $field): ?array
    {
        $typeformType = $field['type'] ?? 'short_text';
        $opnType = self::FIELD_MAP[$typeformType] ?? 'text';

        $property = [
            'id' => $this->generateFieldId(),
            'name' => $this->sanitizeText($field['title'] ?? 'Untitled', 255),
            'type' => $opnType,
            'required' => $field['validations']['required'] ?? false,
            'hidden' => false,
        ];

        switch ($typeformType) {
            case 'long_text':
                $property['multi_lines'] = true;
                break;

            case 'multiple_choice':
            case 'picture_choice':
                $choices = $this->extractChoices($field);
                $allowMultiple = $field['properties']['allow_multiple_selection'] ?? false;

                $property['type'] = $allowMultiple ? 'multi_select' : 'select';
                $property = $this->addSelectOptions($property, $choices);

                if (count($choices) <= 5) {
                    $property['without_dropdown'] = true;
                }
                break;

            case 'dropdown':
            case 'ranking':
                $choices = $this->extractChoices($field);
                $property = $this->addSelectOptions($property, $choices);
                break;

            case 'rating':
                $property['rating_max_value'] = $field['properties']['steps'] ?? 5;
                break;

            case 'opinion_scale':
                $property['type'] = 'scale';
                $property['scale_min_value'] = ($field['properties']['start_at_one'] ?? false) ? 1 : 0;
                $property['scale_max_value'] = $field['properties']['steps'] ?? 10;
                $property['scale_step_value'] = 1;
                break;

            case 'nps':
                $property['type'] = 'scale';
                $property['scale_min_value'] = 0;
                $property['scale_max_value'] = ($field['properties']['steps'] ?? 11) - 1;
                $property['scale_step_value'] = 1;
                break;

            case 'legal':
            case 'yes_no':
                $property['type'] = 'checkbox';
                $property['use_toggle_switch'] = true;
                break;

            case 'checkbox':
                $property['type'] = 'checkbox';
                $choices = $this->extractChoices($field);
                if (!empty($choices)) {
                    $property['name'] = $this->sanitizeText($choices[0], 255);
                }
                break;

            case 'statement':
                $property['type'] = 'nf-text';
                $property['content'] = '<p>' . e($this->sanitizeText($field['title'] ?? '', 2000)) . '</p>';
                unset($property['required'], $property['hidden']);
                break;
        }

        return $property;
    }

    private function extractChoices(array $field): array
    {
        $choices = $field['properties']['choices'] ?? [];

        return array_map(
            fn ($choice) => $this->sanitizeText($choice['label'] ?? ''),
            $choices
        );
    }

    private function addSelectOptions(array $property, array $choices): array
    {
        if (!empty($choices)) {
            $property[$property['type']]['options'] = array_map(
                fn ($label) => ['id' => $this->generateFieldId(), 'name' => $label],
                $choices
            );
        }

        return $property;
    }
}
