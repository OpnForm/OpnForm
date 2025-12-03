<?php

namespace App\Service\AI\Prompts\Form;

use Illuminate\Support\Str;

class FormFieldSchemas
{
    /**
     * Base property schema that all field types extend
     */
    public const BASE_PROPERTY_SCHEMA = [
        'type' => 'object',
        'required' => ['name', 'help', 'hidden', 'required', 'placeholder', 'width'],
        'additionalProperties' => false,
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'The name/label of the field. Always use a name for the block - even for hidden fields & layout blocks (text fields, page breaks, dividers, etc).'
            ],
            'help' => [
                'type' => 'string',
                'description' => 'Help text for the field (default: "" leave empty if not needed)'
            ],
            'hidden' => [
                'type' => 'boolean',
                'description' => 'Whether the field is hidden (default: false)'
            ],
            'required' => [
                'type' => 'boolean',
                'description' => 'Whether the field is required (default: false)'
            ],
            'placeholder' => [
                'type' => 'string',
                'description' => 'Placeholder text for the field. Leave empty if not needed (default: "")'
            ],
            'width' => [
                'type' => 'string',
                'enum' => ['full', '1/2', '1/3', '2/3', '1/4', '3/4'],
                'description' => 'Width of the field in the form layout. "full" takes the entire width, "1/2" takes half width, "1/3" takes a third, etc. (default: "full")',
            ]
        ]
    ];

    /**
     * Complete field type definitions for all available form field types
     */
    public const FIELD_TYPE_DEFINITIONS = [
        'baseProperty' => self::BASE_PROPERTY_SCHEMA,
        'option' => [
            'type' => 'object',
            'required' => ['name', 'id'],
            'additionalProperties' => false,
            'properties' => [
                'name' => ['type' => 'string'],
                'id' => ['type' => 'string']
            ]
        ],
        'selectOptions' => [
            'type' => 'object',
            'required' => ['options'],
            'additionalProperties' => false,
            'properties' => [
                'options' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/definitions/option'],
                    'description' => 'Options for select fields'
                ]
            ]
        ],
        'textProperty' => [
            'type' => 'object',
            'required' => ['type', 'core', 'multi_lines', 'generates_uuid', 'max_char_limit', 'hide_field_name', 'show_char_limit'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['text']],
                'multi_lines' => [
                    'type' => 'boolean',
                    'description' => 'Whether the text field should have multiple lines (default: false)',
                ],
                'generates_uuid' => [
                    'type' => 'boolean',
                    'description' => 'Whether the field should generate a UUID (default: false)'
                ],
                'max_char_limit' => [
                    'type' => ['integer', 'string'],
                    'description' => 'Maximum character limit for text fields (default: 500)'
                ],
                'hide_field_name' => [
                    'type' => 'boolean',
                    'description' => 'Whether to hide the field name (default: false)'
                ],
                'show_char_limit' => [
                    'type' => 'boolean',
                    'description' => 'Whether to show the character limit (default: false)'
                ],
                'core' => ['$ref' => '#/definitions/baseProperty'],
            ]
        ],
        'richTextProperty' => [
            'type' => 'object',
            'required' => ['type', 'core', 'max_char_limit'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['rich_text']],
                'max_char_limit' => [
                    'type' => ['integer', 'string'],
                    'description' => 'Maximum character limit for rich text fields (default: 1000)'
                ],
                'core' => ['$ref' => '#/definitions/baseProperty'],
            ]
        ],
        'dateProperty' => [
            'type' => 'object',
            'required' => ['core', 'type', 'with_time'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['date']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
                'with_time' => [
                    'type' => 'boolean',
                    'description' => 'Whether to include time selection with the date (default: false)',
                ]
            ]
        ],
        'urlProperty' => [
            'type' => 'object',
            'required' => ['core', 'type', 'max_char_limit'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['url']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
                'max_char_limit' => [
                    'type' => ['integer', 'string'],
                    'description' => 'Maximum character limit for URL fields (default: 500)'
                ]
            ]
        ],
        'phoneNumberProperty' => [
            'type' => 'object',
            'required' => ['core', 'type'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['phone_number']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
            ]
        ],
        'emailProperty' => [
            'type' => 'object',
            'required' => ['core', 'type', 'max_char_limit'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['email']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
                'max_char_limit' => [
                    'type' => ['integer', 'string'],
                    'description' => 'Maximum character limit for email fields (default: 320)'
                ]
            ]
        ],
        'checkboxProperty' => [
            'type' => 'object',
            'required' => ['core', 'type', 'use_toggle_switch'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['checkbox']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
                'use_toggle_switch' => [
                    'type' => 'boolean',
                    'description' => 'Whether to display the checkbox as a toggle switch (default: false)',
                ]
            ]
        ],
        'selectProperty' => [
            'type' => 'object',
            'required' => ['core', 'type', 'select', 'without_dropdown'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['select']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
                'select' => ['$ref' => '#/definitions/selectOptions'],
                'without_dropdown' => [
                    'type' => 'boolean',
                    'description' => 'Whether to display select options as radio buttons instead of a dropdown. Recommended for small choices (<5 options) (default: false)',
                ]
            ]
        ],
        'multiSelectProperty' => [
            'type' => 'object',
            'required' => ['core', 'type', 'multi_select', 'without_dropdown'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['multi_select']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
                'multi_select' => ['$ref' => '#/definitions/selectOptions'],
                'without_dropdown' => [
                    'type' => 'boolean',
                    'description' => 'Whether to display multi-select options as checkboxes instead of a dropdown. Recommended for small choices (<5 options) (default: false)',
                ]
            ]
        ],
        'matrixProperty' => [
            'type' => 'object',
            'required' => ['core', 'type', 'rows', 'columns'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['matrix']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
                'rows' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Rows for matrix fields (ex: ["Row 1"])'
                ],
                'columns' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Columns for matrix fields (ex: ["1", "2", "3"])'
                ]
            ]
        ],
        'numberProperty' => [
            'type' => 'object',
            'required' => ['core', 'type'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['number']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
            ]
        ],
        'ratingProperty' => [
            'type' => 'object',
            'required' => ['core', 'type', 'rating_max_value'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['rating']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
                'rating_max_value' => [
                    'type' => 'integer',
                    'description' => 'Maximum rating for rating fields (default: 5)'
                ]
            ]
        ],
        'scaleProperty' => [
            'type' => 'object',
            'required' => ['core', 'type', 'scale_min_value', 'scale_max_value', 'scale_step_value'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['scale']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
                'scale_min_value' => [
                    'type' => 'integer',
                    'description' => 'Minimum value for scale fields (default: 1)'
                ],
                'scale_max_value' => [
                    'type' => 'integer',
                    'description' => 'Maximum value for scale fields (default: 5)'
                ],
                'scale_step_value' => [
                    'type' => 'integer',
                    'description' => 'Step value for scale fields (default: 1)'
                ]
            ]
        ],
        'sliderProperty' => [
            'type' => 'object',
            'required' => ['core', 'type', 'slider_min_value', 'slider_max_value', 'slider_step_value'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['slider']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
                'slider_min_value' => [
                    'type' => 'integer',
                    'description' => 'Minimum value for slider fields (default: 0)'
                ],
                'slider_max_value' => [
                    'type' => 'integer',
                    'description' => 'Maximum value for slider fields (default: 50)'
                ],
                'slider_step_value' => [
                    'type' => 'integer',
                    'description' => 'Step value for slider fields (default: 1)'
                ]
            ]
        ],
        'filesProperty' => [
            'type' => 'object',
            'required' => ['core', 'type'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['files']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
            ]
        ],
        'signatureProperty' => [
            'type' => 'object',
            'required' => ['core', 'type'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['signature']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
            ]
        ],
        'barcodeProperty' => [
            'type' => 'object',
            'required' => ['core', 'type', 'decoders'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['barcode']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
                'decoders' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Decoders for barcode fields (default: ["ean_reader", "ean_8_reader"])'
                ]
            ]
        ],
        'nfTextProperty' => [
            'type' => 'object',
            'required' => ['core', 'type', 'content'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['nf-text']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
                'content' => [
                    'type' => 'string',
                    'description' => 'HTML content for text elements. Supports headers (<h1>, <h2>), formatting (<b>, <i>, <u>, <s>), links (<a>), lists (<ul>, <ol>), colors (<span style="color: #hexcode">), and paragraphs (<p>). Example: "<h1>Form Title</h1><p>Please fill out this form.</p>"'
                ]
            ]
        ],
        'nfPageBreakProperty' => [
            'type' => 'object',
            'required' => ['core', 'type', 'next_btn_text', 'previous_btn_text'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['nf-page-break']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
                'next_btn_text' => [
                    'type' => 'string',
                    'description' => 'Text for the next button in page breaks (default: "Next")'
                ],
                'previous_btn_text' => [
                    'type' => 'string',
                    'description' => 'Text for the previous button in page breaks (default: "Previous")'
                ]
            ]
        ],
        'nfDividerProperty' => [
            'type' => 'object',
            'required' => ['core', 'type'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['nf-divider']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
            ]
        ],
        'nfImageProperty' => [
            'type' => 'object',
            'required' => ['core', 'type'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['nf-image']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
            ]
        ],
        'nfVideoProperty' => [
            'type' => 'object',
            'required' => ['core', 'type'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['nf-video']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
            ]
        ],
        'nfCodeProperty' => [
            'type' => 'object',
            'required' => ['core', 'type'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['nf-code']],
                'core' => ['$ref' => '#/definitions/baseProperty'],
            ]
        ]
    ];

    public static function processFields(array $properties): array
    {
        $processedProperties = [];
        foreach ($properties as $property) {
            $newProperty = $property;
            // Add a unique ID to each property
            $newProperty['id'] = Str::uuid()->toString();

            // Flatten core properties if they exist
            if (isset($property['core']) && is_array($property['core'])) {
                foreach ($property['core'] as $coreKey => $coreValue) {
                    $newProperty[$coreKey] = $coreValue;
                }
                // Remove the core property after flattening
                unset($newProperty['core']);
            }
            $processedProperties[] = $newProperty;
        }

        return $processedProperties;
    }
}
