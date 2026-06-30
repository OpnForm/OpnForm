<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Description('Complete catalog of all available form field types with their configurable properties, default values, and presentation modes. Use this to construct valid field definitions for create-form and draft-form.')]
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
                        'placeholder' => 'string — placeholder text',
                    ],
                ],
                'rich_text' => [
                    'type' => 'rich_text',
                    'title' => 'Rich Text',
                    'description' => 'Rich text editor with formatting',
                    'options' => [
                        'max_char_limit' => 'integer (default 2000)',
                        'placeholder' => 'string',
                    ],
                ],
                'email' => [
                    'type' => 'email',
                    'title' => 'Email',
                    'description' => 'Email address input with validation',
                    'options' => ['placeholder' => 'string'],
                ],
                'url' => [
                    'type' => 'url',
                    'title' => 'URL',
                    'description' => 'URL input with validation',
                    'options' => ['placeholder' => 'string (default "https://example.com")'],
                ],
                'phone_number' => [
                    'type' => 'phone_number',
                    'title' => 'Phone',
                    'description' => 'Phone number input',
                    'options' => ['placeholder' => 'string'],
                ],
                'number' => [
                    'type' => 'number',
                    'title' => 'Number',
                    'description' => 'Numeric input',
                    'options' => ['placeholder' => 'string'],
                ],
                'date' => [
                    'type' => 'date',
                    'title' => 'Date',
                    'description' => 'Date picker',
                    'options' => [],
                ],
                'select' => [
                    'type' => 'select',
                    'title' => 'Select (Dropdown)',
                    'description' => 'Single-select dropdown',
                    'options' => [
                        'placeholder' => 'string',
                        'select.options' => 'array of {name, id} objects — the choices',
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
                    'description' => 'Select multiple options',
                    'options' => [
                        'placeholder' => 'string',
                        'multi_select.options' => 'array of {name, id} objects',
                    ],
                ],
                'checkbox' => [
                    'type' => 'checkbox',
                    'title' => 'Checkbox',
                    'description' => 'Yes/No checkbox',
                    'options' => [],
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
                'matrix' => [
                    'type' => 'matrix',
                    'title' => 'Matrix',
                    'description' => 'Grid/matrix question with rows and columns',
                    'options' => [
                        'rows' => 'array of strings (row labels)',
                        'columns' => 'array of values (column labels)',
                    ],
                ],
            ],
            'layout_blocks' => [
                'nf-text' => [
                    'type' => 'nf-text',
                    'title' => 'Text Block',
                    'description' => 'Static text/content block (not an input)',
                ],
                'nf-page-break' => [
                    'type' => 'nf-page-break',
                    'title' => 'Page Break',
                    'description' => 'Splits form into multiple pages (classic mode only)',
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
            ],
            'common_field_properties' => [
                'name' => 'string (required) — field label shown to users',
                'type' => 'string (required) — one of the types listed above',
                'required' => 'boolean (default false) — whether the field is required',
                'help' => 'string — help text shown below the field',
                'hidden' => 'boolean (default false) — hide field from form',
                'placeholder' => 'string — placeholder text (where applicable)',
            ],
        ];
    }
}
