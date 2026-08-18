<?php

namespace App\Service\Forms;

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

    public static function types(): array
    {
        return [...self::INPUT_TYPES, ...self::LAYOUT_TYPES];
    }

    public static function reference(): array
    {
        return [
            'input_types' => self::INPUT_TYPES,
            'layout_types' => self::LAYOUT_TYPES,
            'aliases' => self::ALIASES,
            'common_properties' => [
                'id' => 'Stable UUID. It is generated when omitted.',
                'name' => 'Required user-facing block label.',
                'type' => 'One canonical type or alias from this catalog.',
                'help' => 'Optional sanitized HTML help text.',
                'hidden' => 'Boolean, defaults to false.',
                'required' => 'Boolean, defaults to false.',
                'placeholder' => 'Optional placeholder.',
                'width' => 'One of full, 1/2, 1/3, 2/3, 1/4, 3/4. Defaults to full.',
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
                'nf-text' => ['content'],
                'nf-page-break' => ['next_btn_text', 'previous_btn_text'],
                'nf-image' => ['image_block'],
                'nf-video' => ['video_block'],
                'nf-audio' => ['audio_block'],
                'nf-code' => ['content'],
            ],
            'availability' => [
                'preview' => 'All field types may be rendered in a draft preview.',
                'save' => 'Workspace plan and hosting rules are applied when a draft is claimed or a form is saved; disabled features are returned as warnings.',
            ],
        ];
    }
}
