<?php

namespace App\Service\AI\Prompts\Form;

class PresentationRules
{
    public const MODE_CLASSIC = 'classic';
    public const MODE_FOCUSED = 'focused';

    public static function buildContext(array $params = []): array
    {
        $mode = $params['presentation_style'] ?? self::MODE_CLASSIC;

        if ($mode === self::MODE_FOCUSED) {
            return [
                'mode' => self::MODE_FOCUSED,
                'allowedFieldTypes' => [
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
                    'nf-text'
                ],
                'constraintsText' => implode("\n", [
                    'Focused mode (Typeform-like):',
                    '- Display one question per page/step, sequentially.',
                    '- Do not use widths (all inputs are full width).',
                    '- Do not use page breaks (nf-page-break).',
                    '- Do not include image (nf-image) or code (nf-code) blocks.',
                    '- Keep copy concise and engaging for each step.'
                ])
            ];
        }

        return [
            'mode' => self::MODE_CLASSIC,
            'allowedFieldTypes' => [
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
                'nf-text',
                'nf-page-break',
                'nf-divider',
                'nf-image',
                'nf-code'
            ],
            'constraintsText' => 'Classic mode: you can use layout widths and optional page breaks.'
        ];
    }
}
