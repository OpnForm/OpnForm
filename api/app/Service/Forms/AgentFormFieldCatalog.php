<?php

namespace App\Service\Forms;

use App\Rules\PropertyValidators\LogicPropertyValidator;

class AgentFormFieldCatalog
{
    public const INPUT_TYPES = [
        'text',
        'rich_text',
        'date',
        'url',
        'phone_number',
        'email',
        'checkbox',
        'select',
        'multi_select',
        'matrix',
        'number',
        'rating',
        'scale',
        'slider',
        'files',
        'signature',
        'barcode',
        'payment',
    ];

    public const LAYOUT_TYPES = [
        'nf-text',
        'nf-page-break',
        'nf-divider',
        'nf-image',
        'nf-video',
        'nf-audio',
        'nf-code',
    ];

    public const ALIASES = [
        'radio' => ['type' => 'select', 'without_dropdown' => true],
        'qrcode' => ['type' => 'barcode', 'decoders' => ['qr_reader']],
        'password' => ['type' => 'text', 'secret_input' => true, 'multi_lines' => false],
        'toggle_switch' => ['type' => 'checkbox', 'use_toggle_switch' => true],
    ];

    public const LEGACY_ALIASES = [
        'textarea' => ['type' => 'text', 'multi_lines' => true],
        'long_text' => ['type' => 'text', 'multi_lines' => true],
        'multi_lines' => ['type' => 'text', 'multi_lines' => true],
        'short_text' => ['type' => 'text', 'multi_lines' => false],
        'multiselect' => ['type' => 'multi_select'],
        'phone' => ['type' => 'phone_number'],
        'file_upload' => ['type' => 'files'],
        'file' => ['type' => 'files'],
        'hidden' => ['type' => 'text', 'hidden' => true],
        'divider' => ['type' => 'nf-divider'],
        'nf_text' => ['type' => 'nf-text'],
        'header' => ['type' => 'nf-text'],
        'info' => ['type' => 'nf-text'],
        'paragraph' => ['type' => 'nf-text'],
        'section_break' => ['type' => 'nf-text'],
        'html' => ['type' => 'nf-text'],
        'page_break' => ['type' => 'nf-page-break'],
        'nf-page_break' => ['type' => 'nf-page-break'],
        'radio_button' => ['type' => 'select', 'without_dropdown' => true],
        'datetime-local' => ['type' => 'date', 'with_time' => true],
        'date_range' => ['type' => 'date', 'date_range' => true],
    ];

    public const OBSOLETE_NON_FIELD_TYPES = [
        'submit',
        'submit_button',
        'nf-submit',
        'captcha',
        'use_captcha',
    ];

    public static function types(): array
    {
        return [...self::INPUT_TYPES, ...self::LAYOUT_TYPES];
    }

    public static function normalizationAliases(): array
    {
        return array_merge(self::ALIASES, self::LEGACY_ALIASES);
    }

    /** @return array<string, array<int, string>> */
    public static function logicOperatorsByReferenceType(): array
    {
        return collect(LogicPropertyValidator::getConditionMapping())
            ->map(function (array $mapping): array {
                return collect($mapping['comparators'] ?? [])
                    ->reject(fn (mixed $config): bool => is_array($config)
                        && ($config['custom_validation_only'] ?? false) === true)
                    ->keys()
                    ->values()
                    ->all();
            })
            ->all();
    }

    /** @return array<int, string> */
    public static function logicOperators(): array
    {
        return collect(self::logicOperatorsByReferenceType())
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    public static function reference(): array
    {
        return [
            'input_types' => self::INPUT_TYPES,
            'layout_types' => self::LAYOUT_TYPES,
            'aliases' => self::ALIASES,
            'common_properties' => [
                'id' => 'Stable technical identifier. Omit it when creating a block so the server generates one. Never place this value in the visible label.',
                'name' => 'Required respondent-facing label in sentence case, using natural words and spaces, for example Full name or Email address. Never use snake_case, kebab-case, database keys, or variable names.',
                'type' => 'One canonical type or alias from this catalog.',
                'help' => 'Optional sanitized HTML help text.',
                'hidden' => 'Boolean, defaults to false.',
                'required' => 'Boolean, defaults to false.',
                'placeholder' => 'Optional placeholder.',
                'width' => 'One of full, 1/2, 1/3, 2/3, 1/4, 3/4. Defaults to full.',
                'logic' => 'Optional display logic object described in display_logic. Empty or invalid logic that can be removed safely is discarded during normalization.',
            ],
            'type_properties' => [
                'text' => ['multi_lines', 'max_char_limit', 'show_char_limit', 'secret_input', 'input_mask'],
                'date' => ['with_time', 'date_range', 'prefill_today', 'disable_past_dates', 'disable_future_dates'],
                'select' => ['select.options[{name,id,image?}]', 'without_dropdown', 'allow_creation'],
                'multi_select' => ['multi_select.options[{name,id,image?}]', 'without_dropdown', 'min_selection', 'max_selection'],
                'checkbox' => ['use_toggle_switch'],
                'rating' => ['rating_max_value'],
                'scale' => ['scale_min_value', 'scale_max_value', 'scale_step_value'],
                'slider' => ['slider_min_value', 'slider_max_value', 'slider_step_value'],
                'files' => ['max_file_size', 'allowed_file_types'],
                'barcode' => ['decoders'],
                'matrix' => ['rows', 'columns'],
                'payment' => ['amount', 'currency', 'stripe_account_id'],
                'nf-text' => [
                    'content' => 'Sanitized HTML fragment, never Markdown. Use elements such as <h1>, <h2>, <p>, <strong>, <em>, <a>, <ul>, <ol>, and <li>. Example: <h1>Contact us</h1><p>Send us a message and we will get back to you.</p>.',
                ],
                'nf-page-break' => ['next_btn_text', 'previous_btn_text'],
                'nf-image' => ['image_block'],
                'nf-video' => ['video_block'],
                'nf-audio' => ['audio_block'],
                'nf-code' => ['content'],
            ],
            'presentation_modes' => [
                'classic' => [
                    'behavior' => 'Renders fields in a continuous form. Add nf-page-break blocks only when explicit pagination is wanted.',
                    'widths' => 'Responsive widths such as 1/2 and 1/3 may be used.',
                    'standalone_layout_blocks' => 'Layout blocks such as nf-image, nf-divider, and nf-code are supported.',
                ],
                'focused' => [
                    'behavior' => 'Typeform-like flow. Every visible block is already one sequential step; do not add nf-page-break blocks.',
                    'blocks' => 'Use input blocks and concise nf-text intro or explanation steps. Do not use standalone nf-image, nf-divider, nf-code, nf-video, or nf-audio blocks.',
                    'widths' => 'Use width full because each step renders one block.',
                    'media' => 'To illustrate a step, attach an image object directly to that input or nf-text block.',
                    'settings' => [
                        'auto_next' => 'Boolean. When true, compatible selection controls advance after input.',
                        'navigation_arrows' => 'Boolean. Shows previous and next arrow controls.',
                    ],
                    'translations' => [
                        'focused_next_button_text' => 'Localized label for the next-step button.',
                    ],
                ],
            ],
            'form_style' => [
                'theme' => ['default', 'simple', 'notion', 'minimal', 'transparent'],
                'width' => ['centered', 'full'],
                'size' => ['sm', 'md', 'lg'],
                'border_radius' => ['none', 'small', 'full'],
                'dark_mode' => ['auto', 'light', 'dark'],
                'color' => 'Accent color string, preferably a hex color such as #2563EB.',
                'uppercase_labels' => 'Boolean.',
                'show_progress_bar' => 'Boolean.',
            ],
            'computed_variables' => [
                'description' => 'Optional calculated values that formulas and display logic may reference. Invalid variables and variables depending on them are removed during normalization.',
                'item' => [
                    'id' => 'Required unique technical identifier beginning with cv_. It must not duplicate a block ID.',
                    'name' => 'Required unique human-readable name, at most 100 characters.',
                    'formula' => 'Required expression, at most 2000 characters. Reference a field or another variable with braces, for example {budget} * 1.2.',
                    'result_type' => 'Optional hint: number, text, or auto.',
                ],
                'example' => [
                    'id' => 'cv_priority_score',
                    'name' => 'Priority score',
                    'formula' => '{budget} * 1.2',
                    'result_type' => 'number',
                ],
            ],
            'display_logic' => [
                'description' => 'Attach logic to the target block. Conditions may reference another field or a computed variable, never the target field itself.',
                'shape' => [
                    'conditions' => 'A leaf condition or a group with operatorIdentifier and/or plus a non-empty children array. Groups may be nested up to 10 levels and contain at most 100 total nodes.',
                    'actions' => LogicPropertyValidator::ACTIONS_VALUES,
                ],
                'operators_by_reference_type' => self::logicOperatorsByReferenceType(),
                'computed_variable_reference_type' => 'Use property_meta.type computed for every computed-variable condition.',
                'example' => [
                    'conditions' => [
                        'operatorIdentifier' => 'and',
                        'children' => [[
                            'identifier' => 'cv_priority_score',
                            'value' => [
                                'operator' => 'greater_than',
                                'property_meta' => ['id' => 'cv_priority_score', 'type' => 'computed'],
                                'value' => 10000,
                            ],
                        ]],
                    ],
                    'actions' => ['show-block'],
                ],
            ],
            'authoring_guidelines' => AgentFormAuthoringGuide::reference(),
            'block_media' => [
                'property' => 'image',
                'attach_to' => 'Any input block or nf-text block, especially for focused steps. This differs from the classic-only nf-image standalone block and its image_block property.',
                'image' => [
                    'url' => 'Absolute public HTTPS image URL. Required for the media to render.',
                    'alt' => 'Descriptive alternative text, at most 125 characters.',
                    'layout' => 'One of between, left-small, right-small, left-split, right-split, background.',
                    'focal_point' => ['x' => 'Number from 0 to 100.', 'y' => 'Number from 0 to 100.'],
                    'brightness' => 'Integer from -100 to 100.',
                    'fade' => 'Optional boolean overlay fade.',
                ],
                'asset_policy' => 'Use user-provided or durable public HTTPS URLs. Never persist localhost, private addresses, expiring signed URLs, temporary tunnel domains such as trycloudflare.com, or paths discovered in the OpnForm source tree.',
            ],
            'availability' => [
                'preview' => 'All field types may be rendered in a draft preview.',
                'save' => 'Workspace plan and hosting rules are applied when a draft is claimed or a form is saved; disabled features are returned as warnings.',
            ],
        ];
    }
}
