<?php

namespace App\Service\AI\Mcp;

use App\Service\AI\Prompts\Form\FormFieldSchemas;

class FormStateNormalizationService
{
    public function normalize(array $formState): array
    {
        if (empty($formState)) {
            return $formState;
        }

        $normalized = array_replace([
            'title' => 'New Form',
            're_fillable' => false,
            'use_captcha' => false,
            'redirect_url' => null,
            'submitted_text' => '<p>Thank you for your submission!</p>',
            'uppercase_labels' => false,
            'submit_button_text' => 'Submit',
            're_fill_button_text' => 'Fill Again',
            'color' => '#64748b',
            'properties' => [],
        ], $formState);

        $properties = is_array($normalized['properties'] ?? null) ? $normalized['properties'] : [];
        $properties = FormFieldSchemas::processFields($properties);

        $normalized['properties'] = array_map(function (array $property) {
            if (! isset($property['name']) || trim((string) $property['name']) === '') {
                $type = (string) ($property['type'] ?? 'field');
                $property['name'] = ucfirst(str_replace(['nf-', '_', '-'], ['', ' ', ' '], $type));
            }

            return $property;
        }, $properties);

        return $normalized;
    }
}
