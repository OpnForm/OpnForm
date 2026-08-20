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
