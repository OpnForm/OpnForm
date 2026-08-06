<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('Field Types')]
#[Description('Complete catalog of all available form field types with their configurable properties, default values, and presentation modes. Use this to construct valid field definitions for create-form.')]
#[Uri('opnform://reference/field-types')]
#[MimeType('application/json')]
class FieldTypesResource extends Resource
{
    public function handle(Request $request): Response
    {
        $fieldTypes = $this->getFieldTypes();

        return Response::text(json_encode($fieldTypes, JSON_PRETTY_PRINT));
    }

    private function getFieldTypes(): array
    {
        return [
            'input_fields' => [
                'text' => [
                    'type' => 'text',
                    'title' => 'Text',
                    'description' => 'Single or multi-line text input',
                    'options' => [
                        'multi_lines' => 'boolean (default false) — allow multi-line input',
                        'max_char_limit' => 'integer (default 2000) — max characters allowed',
                        'generates_uuid' => 'boolean (default false) — auto-generate a UUID value',
                        'hide_field_name' => 'boolean (default false) — hide the field label',
                        'show_char_limit' => 'boolean (default false) — show character counter to user',
                    ],
                ],
                'rich_text' => [
                    'type' => 'rich_text',
                    'title' => 'Rich Text',
                    'description' => 'Rich text editor with formatting',
                    'options' => [
                        'max_char_limit' => 'integer (default 2000)',
                    ],
                ],
                'email' => [
                    'type' => 'email',
                    'title' => 'Email',
                    'description' => 'Email address input with validation',
                    'options' => [
                        'max_char_limit' => 'integer (default 2000)',
                    ],
                ],
                'url' => [
                    'type' => 'url',
                    'title' => 'URL',
                    'description' => 'URL input with validation',
                    'options' => [
                        'max_char_limit' => 'integer (default 2000)',
                    ],
                ],
                'phone_number' => [
                    'type' => 'phone_number',
                    'title' => 'Phone',
                    'description' => 'Phone number input',
                    'options' => [],
                ],
                'number' => [
                    'type' => 'number',
                    'title' => 'Number',
                    'description' => 'Numeric input',
                    'options' => [],
                ],
                'date' => [
                    'type' => 'date',
                    'title' => 'Date',
                    'description' => 'Date picker with optional time selection',
                    'options' => [
                        'with_time' => 'boolean (default false) — include time selection',
                    ],
                ],
                'select' => [
                    'type' => 'select',
                    'title' => 'Select (Dropdown)',
                    'description' => 'Single-select dropdown. Use without_dropdown for radio button display.',
                    'options' => [
                        'select.options' => 'array of {name, id} objects — the choices',
                        'without_dropdown' => 'boolean (default false) — display as radio buttons instead of dropdown (recommended for <5 options)',
                    ],
                    'example' => [
                        'type' => 'select',
                        'name' => 'Favorite color',
                        'select' => [
                            'options' => [
                                ['name' => 'Red', 'id' => 'Red'],
                                ['name' => 'Blue', 'id' => 'Blue'],
                                ['name' => 'Green', 'id' => 'Green'],
                            ],
                        ],
                    ],
                ],
                'multi_select' => [
                    'type' => 'multi_select',
                    'title' => 'Multi-select',
                    'description' => 'Select multiple options. Use without_dropdown for checkbox display.',
                    'options' => [
                        'multi_select.options' => 'array of {name, id} objects',
                        'without_dropdown' => 'boolean (default false) — display as checkboxes instead of dropdown (recommended for <5 options)',
                    ],
                ],
                'checkbox' => [
                    'type' => 'checkbox',
                    'title' => 'Checkbox',
                    'description' => 'Yes/No checkbox. Use use_toggle_switch for toggle display.',
                    'options' => [
                        'use_toggle_switch' => 'boolean (default false) — display as toggle switch',
                    ],
                ],
                'rating' => [
                    'type' => 'rating',
                    'title' => 'Rating',
                    'description' => 'Star rating',
                    'options' => ['rating_max_value' => 'integer (default 5)'],
                ],
                'scale' => [
                    'type' => 'scale',
                    'title' => 'Scale',
                    'description' => 'Numeric scale (e.g. 1-10)',
                    'options' => [
                        'scale_min_value' => 'integer (default 1)',
                        'scale_max_value' => 'integer (default 5)',
                        'scale_step_value' => 'integer (default 1)',
                    ],
                ],
                'slider' => [
                    'type' => 'slider',
                    'title' => 'Slider',
                    'description' => 'Slider input for numeric range',
                    'options' => [
                        'slider_min_value' => 'integer (default 0)',
                        'slider_max_value' => 'integer (default 50)',
                        'slider_step_value' => 'integer (default 1)',
                    ],
                ],
                'files' => [
                    'type' => 'files',
                    'title' => 'File Upload',
                    'description' => 'File upload field',
                    'options' => [],
                ],
                'signature' => [
                    'type' => 'signature',
                    'title' => 'Signature',
                    'description' => 'Signature pad',
                    'options' => [],
                ],
                'barcode' => [
                    'type' => 'barcode',
                    'title' => 'Barcode Reader',
                    'description' => 'Barcode/QR code scanner',
                    'options' => [
                        'decoders' => 'array of strings (default ["qr_reader","ean_reader","ean_8_reader"]) — barcode formats to recognize',
                    ],
                ],
                'matrix' => [
                    'type' => 'matrix',
                    'title' => 'Matrix',
                    'description' => 'Grid/matrix question with rows and columns',
                    'options' => [
                        'rows' => 'array of strings (row labels)',
                        'columns' => 'array of values (column labels)',
                    ],
                ],
                'payment' => [
                    'type' => 'payment',
                    'title' => 'Payment',
                    'description' => 'Stripe payment field (max 1 per form, requires authentication, cloud-hosted only)',
                    'options' => [],
                ],
            ],
            'layout_blocks' => [
                'nf-text' => [
                    'type' => 'nf-text',
                    'title' => 'Text Block',
                    'description' => 'Static rich-text content block (not an input)',
                    'options' => [
                        'content' => 'string (HTML) — supports headers, formatting, links, lists, colors, paragraphs',
                    ],
                ],
                'nf-page-break' => [
                    'type' => 'nf-page-break',
                    'title' => 'Page Break',
                    'description' => 'Splits form into multiple pages (classic mode only)',
                    'options' => [
                        'next_btn_text' => 'string (default "Next") — next button label',
                        'previous_btn_text' => 'string (default "Previous") — previous button label',
                    ],
                ],
                'nf-divider' => [
                    'type' => 'nf-divider',
                    'title' => 'Divider',
                    'description' => 'Horizontal divider line (classic mode only)',
                ],
                'nf-image' => [
                    'type' => 'nf-image',
                    'title' => 'Image',
                    'description' => 'Image block (classic mode only)',
                ],
                'nf-video' => [
                    'type' => 'nf-video',
                    'title' => 'Video',
                    'description' => 'Embeddable video block',
                ],
                'nf-code' => [
                    'type' => 'nf-code',
                    'title' => 'Code Block',
                    'description' => 'Code snippet block',
                ],
            ],
            'shortcut_types' => [
                'radio' => [
                    'description' => 'Radio buttons — shortcut for select with without_dropdown: true',
                    'actual_type' => 'select',
                    'default_values' => ['without_dropdown' => true],
                ],
                'qrcode' => [
                    'description' => 'QR Code reader — shortcut for barcode with decoders: ["qr_reader"]',
                    'actual_type' => 'barcode',
                    'default_values' => ['decoders' => ['qr_reader']],
                ],
                'password' => [
                    'description' => 'Password input — shortcut for text with secret_input: true',
                    'actual_type' => 'text',
                    'default_values' => ['secret_input' => true, 'multi_lines' => false],
                ],
                'toggle_switch' => [
                    'description' => 'Toggle switch — shortcut for checkbox with use_toggle_switch: true',
                    'actual_type' => 'checkbox',
                    'default_values' => ['use_toggle_switch' => true],
                ],
            ],
            'common_field_properties' => [
                'name' => 'string (required) — field label shown to users',
                'type' => 'string (required) — one of the types listed above',
                'required' => 'boolean (default false) — whether the field is required',
                'help' => 'string — help text shown below the field',
                'hidden' => 'boolean (default false) — hide field from form',
                'placeholder' => 'string — placeholder text (where applicable)',
                'width' => 'string (default "full") — field width in layout: "full", "1/2", "1/3", "2/3", "1/4", "3/4"',
            ],
        ];
    }
}
