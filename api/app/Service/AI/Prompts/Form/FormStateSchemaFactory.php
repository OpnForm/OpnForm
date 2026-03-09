<?php

namespace App\Service\AI\Prompts\Form;

class FormStateSchemaFactory
{
    public const SCHEMA_VERSION = 'v1';

    public static function buildFullFormSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title', 'properties', 're_fillable', 'use_captcha', 'redirect_url', 'submitted_text', 'uppercase_labels', 'submit_button_text', 're_fill_button_text', 'color'],
            'additionalProperties' => false,
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'The title of the form (default: "New Form")'
                ],
                're_fillable' => [
                    'type' => 'boolean',
                    'description' => 'Whether the form can be refilled after submission (default: false)'
                ],
                'use_captcha' => [
                    'type' => 'boolean',
                    'description' => 'Whether to use CAPTCHA for spam protection (default: false)'
                ],
                'redirect_url' => [
                    'type' => ['string', 'null'],
                    'description' => 'URL to redirect to after submission (default: null)'
                ],
                'submitted_text' => [
                    'type' => 'string',
                    'description' => 'Text to display after form submission (default: "<p>Thank you for your submission!</p>")'
                ],
                'uppercase_labels' => [
                    'type' => 'boolean',
                    'description' => 'Whether to display field labels in uppercase (default: false)'
                ],
                'submit_button_text' => [
                    'type' => 'string',
                    'description' => 'Text for the submit button (default: "Submit")'
                ],
                're_fill_button_text' => [
                    'type' => 'string',
                    'description' => 'Text for the refill button (default: "Fill Again")'
                ],
                'color' => [
                    'type' => 'string',
                    'description' => 'Primary color for the form (default: "#64748b")'
                ],
                'properties' => [
                    'type' => 'array',
                    'description' => 'Array of form fields and elements',
                    'items' => [
                        'anyOf' => self::fieldRefs(),
                    ],
                ],
            ],
            'definitions' => FormFieldSchemas::FIELD_TYPE_DEFINITIONS,
        ];
    }

    public static function buildFieldsOnlySchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['properties'],
            'additionalProperties' => false,
            'properties' => [
                'properties' => [
                    'type' => 'array',
                    'description' => 'Array of form fields and elements',
                    'items' => [
                        'anyOf' => self::fieldRefs(),
                    ],
                ],
            ],
            'definitions' => FormFieldSchemas::FIELD_TYPE_DEFINITIONS,
        ];
    }

    private static function fieldRefs(): array
    {
        return [
            ['$ref' => '#/definitions/textProperty'],
            ['$ref' => '#/definitions/richTextProperty'],
            ['$ref' => '#/definitions/dateProperty'],
            ['$ref' => '#/definitions/urlProperty'],
            ['$ref' => '#/definitions/phoneNumberProperty'],
            ['$ref' => '#/definitions/emailProperty'],
            ['$ref' => '#/definitions/checkboxProperty'],
            ['$ref' => '#/definitions/selectProperty'],
            ['$ref' => '#/definitions/multiSelectProperty'],
            ['$ref' => '#/definitions/matrixProperty'],
            ['$ref' => '#/definitions/numberProperty'],
            ['$ref' => '#/definitions/ratingProperty'],
            ['$ref' => '#/definitions/scaleProperty'],
            ['$ref' => '#/definitions/sliderProperty'],
            ['$ref' => '#/definitions/filesProperty'],
            ['$ref' => '#/definitions/signatureProperty'],
            ['$ref' => '#/definitions/barcodeProperty'],
            ['$ref' => '#/definitions/nfTextProperty'],
            ['$ref' => '#/definitions/nfPageBreakProperty'],
            ['$ref' => '#/definitions/nfDividerProperty'],
            ['$ref' => '#/definitions/nfImageProperty'],
            ['$ref' => '#/definitions/nfVideoProperty'],
            ['$ref' => '#/definitions/nfCodeProperty'],
        ];
    }
}
