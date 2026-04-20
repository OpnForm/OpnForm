<?php

namespace App\Service\FormImport\Importers;

use App\Service\FormImport\FormImportException;

class TallyImporter extends AbstractImporter
{
    private const FIELD_MAP = [
        'INPUT_TEXT' => 'text',
        'TEXTAREA' => 'text',
        'INPUT_NUMBER' => 'number',
        'INPUT_EMAIL' => 'email',
        'INPUT_LINK' => 'url',
        'INPUT_PHONE_NUMBER' => 'phone_number',
        'INPUT_DATE' => 'date',
        'INPUT_TIME' => 'date',
        'LINEAR_SCALE' => 'scale',
        'RATING' => 'rating',
        'FILE_UPLOAD' => 'files',
        'SIGNATURE' => 'signature',
    ];

    private const CONTAINER_TYPES = [
        'MULTIPLE_CHOICE',
        'DROPDOWN',
        'CHECKBOXES',
        'MULTI_SELECT',
        'RANKING',
        'MATRIX',
    ];

    private const TEXT_TYPES = [
        'TEXT',
        'TITLE',
        'LABEL',
        'HEADING_1',
        'HEADING_2',
        'HEADING_3',
    ];

    private const CHILD_OPTION_TYPES = [
        'MULTIPLE_CHOICE_OPTION',
        'DROPDOWN_OPTION',
        'CHECKBOX',
        'MULTI_SELECT_OPTION',
        'RANKING_OPTION',
        'MATRIX_ROW',
        'MATRIX_COLUMN',
    ];

    private const SKIP_TYPES = [
        'FORM_TITLE',
        'QUESTION',
        'IMAGE',
        'EMBED',
        'EMBED_VIDEO',
        'EMBED_AUDIO',
        'PAYMENT',
        'WALLET_CONNECT',
        'HIDDEN_FIELDS',
        'CONDITIONAL_LOGIC',
        'CALCULATED_FIELDS',
        'CAPTCHA',
        'RESPONDENT_COUNTRY',
    ];

    public function import(array $importData): array
    {
        $html = $this->fetchHtml($importData['url']);
        $data = $this->extractNextData($html);

        return $this->parseFormData($data);
    }

    public function allowedDomains(): array
    {
        return ['tally.so'];
    }

    private function parseFormData(array $data): array
    {
        $pageProps = $data['props']['pageProps'] ?? null;
        if (! $pageProps) {
            throw new FormImportException('Could not find form data in the page structure.');
        }

        $blocks = $pageProps['blocks'] ?? $pageProps['form']['blocks'] ?? [];
        if (! is_array($blocks) || $blocks === []) {
            throw new FormImportException('Could not find Tally form blocks in the page data.');
        }

        $title = $this->sanitizeText($pageProps['name'] ?? $pageProps['title'] ?? 'Imported Tally Form', 60);
        ['properties' => $properties, 'settings' => $settings] = $this->mapBlocks($blocks);

        return [
            'title' => $title,
            'properties' => $properties,
            ...$this->compactSettings($settings),
        ];
    }

    /**
     * Drop empty form-level attributes (submitted_text, …)
     */
    private function compactSettings(array $settings): array
    {
        return array_filter(
            $settings,
            fn ($value) => ! ($value === null || $value === '' || $value === [])
        );
    }

    private function mapBlocks(array $blocks): array
    {
        $optionIndex = $this->indexOptionBlocks($blocks);
        $properties = [];
        $processedGroups = [];
        $pendingLabel = null;

        // Form-level attributes that we collect while iterating blocks.
        $settings = [
            'submitted_text' => '',
        ];

        $inThankYouPage = false;

        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';

            if ($inThankYouPage) {
                $settings['submitted_text'] .= $this->renderThankYouBlock($block);

                continue;
            }

            if ($this->isThankYouPageBreak($block)) {
                $inThankYouPage = true;

                continue;
            }

            if (! $type || in_array($type, self::SKIP_TYPES, true)) {
                continue;
            }

            // Standalone label blocks (Tally's "Question label" widget) describe
            // the next input instead of being standalone content; hold the text
            // so the next mapped field can adopt it as its name.
            if ($this->isQuestionLabelBlock($block)) {
                $labelText = trim($this->flattenSafeHTMLSchema($block['payload']['safeHTMLSchema'] ?? null));
                if ($labelText !== '') {
                    $pendingLabel = $this->sanitizeText($labelText, 255);
                }

                continue;
            }

            // Tally __NEXT_DATA__ may omit the parent container for select-type
            // questions and only include child option blocks. Synthesize the
            // parent from the first child we encounter for each group.
            if (in_array($type, self::CHILD_OPTION_TYPES, true)) {
                $groupUuid = $block['groupUuid'] ?? '';
                if ($groupUuid === '' || isset($processedGroups[$groupUuid])) {
                    continue;
                }
                $processedGroups[$groupUuid] = true;
                $this->synthesizeParentField($block, $optionIndex, $pendingLabel, $properties);
                $pendingLabel = null;

                continue;
            }

            // Real container blocks mark their group so children don't duplicate
            if (in_array($type, self::CONTAINER_TYPES, true)) {
                $processedGroups[$block['groupUuid'] ?? $block['uuid'] ?? ''] = true;
            }

            $mapped = $this->mapBlock($block, $optionIndex);
            if ($mapped !== null) {
                if ($pendingLabel !== null && $this->hasDefaultName($mapped)) {
                    $mapped['name'] = $pendingLabel;
                }
                $properties[] = $mapped;
                $pendingLabel = null;
            }

            foreach ($block['groupBlocks'] ?? [] as $child) {
                $ct = $child['type'] ?? '';
                if (in_array($ct, self::CHILD_OPTION_TYPES, true) || in_array($ct, self::SKIP_TYPES, true)) {
                    continue;
                }
                $childMapped = $this->mapBlock($child, $optionIndex);
                if ($childMapped !== null) {
                    $properties[] = $childMapped;
                }
            }
        }

        return [
            'properties' => $properties,
            'settings' => $settings,
        ];
    }

    /**
     * Standalone label widgets are either a TEXT_TYPES block whose group is
     * explicitly LABEL or a question heading that Tally groups under QUESTION.
     */
    private function isQuestionLabelBlock(array $block): bool
    {
        $type = $block['type'] ?? '';
        $groupType = $block['groupType'] ?? '';

        return in_array($type, self::TEXT_TYPES, true)
            && in_array($groupType, ['LABEL', 'QUESTION'], true);
    }

    private function hasDefaultName(array $property): bool
    {
        $name = $property['name'] ?? '';
        $placeholder = $property['placeholder'] ?? null;

        return $name === ''
            || $name === 'Untitled'
            || $name === 'Checkbox'
            || ($placeholder !== null && $name === $placeholder);
    }

    /**
     * When Tally omits a parent container block, build a virtual parent from
     * the first child option block's groupType / groupUuid.
     */
    private function synthesizeParentField(
        array $childBlock,
        array $optionIndex,
        ?string $pendingLabel,
        array &$properties,
    ): void {
        $groupUuid = $childBlock['groupUuid'] ?? '';
        $groupType = $childBlock['groupType'] ?? '';
        $siblingOptions = $optionIndex[$groupUuid] ?? [];

        // Tally "CHECKBOX" blocks can appear on their own (terms & conditions,
        // newsletter opt-in) — map those to OpnForm's boolean checkbox instead
        // of a single-option multi_select.
        if ($groupType === 'CHECKBOXES' && count($siblingOptions) <= 1) {
            $properties[] = $this->buildStandaloneCheckbox($childBlock, $pendingLabel);

            return;
        }

        $payload = array_merge(
            $childBlock['payload'] ?? [],
            $pendingLabel !== null && $pendingLabel !== '' ? ['name' => $pendingLabel] : []
        );

        $mapped = $this->mapBlock([
            'uuid' => $groupUuid,
            'groupUuid' => $groupUuid,
            'type' => $groupType,
            'payload' => $payload,
        ], $optionIndex);

        if ($mapped !== null) {
            $properties[] = $mapped;
        }
    }

    private function buildStandaloneCheckbox(array $childBlock, ?string $pendingLabel): array
    {
        $optionText = $this->extractOptionText($childBlock);
        $name = $pendingLabel !== null && $pendingLabel !== ''
            ? $pendingLabel
            : ($optionText !== '' ? $optionText : 'Checkbox');

        $payload = $childBlock['payload'] ?? [];

        $property = [
            'id' => $this->generateFieldId(),
            'name' => $name,
            'type' => 'checkbox',
            'required' => (bool) ($payload['isRequired'] ?? false),
            'hidden' => (bool) ($payload['isHidden'] ?? false),
        ];

        if ($pendingLabel !== null && $pendingLabel !== '' && $optionText !== '' && $optionText !== $pendingLabel) {
            $property['help'] = $this->sanitizeText($optionText, 1000);
        }

        return $property;
    }

    // Block → property routing
    private function mapBlock(array $block, array $optionIndex): ?array
    {
        $type = $block['type'] ?? null;
        if (! $type) {
            return null;
        }

        if (in_array($type, ['MULTIPLE_CHOICE', 'DROPDOWN', 'CHECKBOXES', 'MULTI_SELECT'], true)) {
            return $this->mapSelectType($block, $optionIndex);
        }
        if ($type === 'RANKING') {
            return $this->mapRanking($block, $optionIndex);
        }
        if ($type === 'MATRIX') {
            return $this->mapMatrix($block, $optionIndex);
        }
        if (in_array($type, self::TEXT_TYPES, true)) {
            return $this->mapTextBlock($block);
        }
        if ($type === 'PAGE_BREAK' || $type === 'DIVIDER') {
            return $this->mapDecoration($type);
        }

        return $this->mapSimpleInput($block, $type);
    }

    // Container field mappers
    private function mapSelectType(array $block, array $optionIndex): ?array
    {
        $type = $block['type'];
        $options = $this->optionBlocksForParent($block, $optionIndex);
        $labels = $this->extractOptionLabels($options);

        $opnType = match ($type) {
            'CHECKBOXES', 'MULTI_SELECT' => 'multi_select',
            'MULTIPLE_CHOICE' => ! empty($options[0]['payload']['allowMultiple']) ? 'multi_select' : 'select',
            default => 'select',
        };

        $property = $this->baseProperty($block, $opnType);
        $property = $this->addSelectOptions($property, $labels);

        if ($type !== 'DROPDOWN' && ($type === 'CHECKBOXES' || count($labels) <= 5)) {
            $property['without_dropdown'] = true;
        }

        return $property;
    }

    private function mapRanking(array $block, array $optionIndex): ?array
    {
        $labels = $this->extractOptionLabels($this->optionBlocksForParent($block, $optionIndex));
        $joined = implode(', ', array_filter($labels));

        $property = $this->baseProperty($block, 'text', 'Ranking');
        $property['help'] = $joined !== ''
            ? 'Imported Tally ranking; original items: ' . $this->sanitizeText($joined, 2000)
            : 'Imported Tally ranking question.';

        return $property;
    }

    private function mapMatrix(array $block, array $optionIndex): ?array
    {
        $rows = [];
        $columns = [];
        foreach ($this->optionBlocksForParent($block, $optionIndex) as $member) {
            $text = $this->extractOptionText($member);
            if ($text === '') {
                continue;
            }
            match ($member['type'] ?? '') {
                'MATRIX_ROW' => $rows[] = $text,
                'MATRIX_COLUMN' => $columns[] = $text,
                default => null,
            };
        }

        $property = $this->baseProperty($block, 'matrix', 'Matrix');
        $property['rows'] = $rows;
        $property['columns'] = $columns;

        return $property;
    }

    // Simple field mappers
    private function mapSimpleInput(array $block, string $tallyType): ?array
    {
        $opnType = self::FIELD_MAP[$tallyType] ?? null;
        if ($opnType === null) {
            if (str_starts_with($tallyType, 'INPUT_')) {
                $opnType = 'text';
            } else {
                return null;
            }
        }

        $property = $this->baseProperty($block, $opnType);
        $payload = $block['payload'] ?? [];

        switch ($tallyType) {
            case 'TEXTAREA':
                $property['multi_lines'] = true;
                break;
            case 'INPUT_TIME':
                $property['with_time'] = true;
                break;
            case 'LINEAR_SCALE':
                $property['scale_min_value'] = (int) ($payload['start'] ?? 0);
                $property['scale_max_value'] = (int) ($payload['end'] ?? 10);
                $property['scale_step_value'] = (int) ($payload['step'] ?? 1);
                if ($property['scale_max_value'] <= $property['scale_min_value']) {
                    $property['scale_max_value'] = $property['scale_min_value'] + 10;
                }
                break;
            case 'RATING':
                $property['rating_max_value'] = max(1, min(10, (int) ($payload['stars'] ?? 5)));
                break;
            case 'FILE_UPLOAD':
                if (! empty($payload['hasMultipleFiles'])) {
                    $property['help'] = 'Imported file upload: multiple files were allowed in Tally.';
                }
                break;
        }

        return $property;
    }

    private function mapTextBlock(array $block): ?array
    {
        $content = $this->safeHTMLSchemaToContent($block['payload']['safeHTMLSchema'] ?? null);
        if ($content === '') {
            return null;
        }

        return [
            'id' => $this->generateFieldId(),
            'name' => $this->extractBlockName($block, 'Text'),
            'type' => 'nf-text',
            'content' => $content,
        ];
    }

    private function mapDecoration(string $tallyType): array
    {
        $property = [
            'id' => $this->generateFieldId(),
            'name' => $tallyType === 'DIVIDER' ? 'Divider' : 'Page Break',
            'type' => $tallyType === 'DIVIDER' ? 'nf-divider' : 'nf-page-break',
        ];

        if ($tallyType === 'DIVIDER') {
            $property['hidden'] = false;
        }

        return $property;
    }

    // Option index & lookup
    private function indexOptionBlocks(array $blocks): array
    {
        $all = $blocks;
        foreach ($blocks as $block) {
            foreach ($block['groupBlocks'] ?? [] as $child) {
                $all[] = $child;
            }
        }

        $index = [];
        foreach ($all as $block) {
            if (! in_array($block['type'] ?? '', self::CHILD_OPTION_TYPES, true)) {
                continue;
            }
            $parentId = $block['groupUuid'] ?? null;
            if ($parentId && is_string($parentId)) {
                $index[$parentId][] = $block;
            }
        }

        foreach ($index as &$opts) {
            usort($opts, fn ($a, $b) => ($a['payload']['index'] ?? 0) <=> ($b['payload']['index'] ?? 0));
        }

        return $index;
    }

    /**
     * Children normally reference the parent's uuid via groupUuid, but some
     * __NEXT_DATA__ payloads key them to the parent's own groupUuid instead.
     */
    private function optionBlocksForParent(array $block, array $optionIndex): array
    {
        $uuid = $block['uuid'] ?? '';
        $groupUuid = $block['groupUuid'] ?? '';

        if ($uuid !== '' && ! empty($optionIndex[$uuid])) {
            return $optionIndex[$uuid];
        }

        if ($groupUuid !== '' && $groupUuid !== $uuid && ! empty($optionIndex[$groupUuid])) {
            return $optionIndex[$groupUuid];
        }

        return [];
    }

    private function baseProperty(array $block, string $opnType, string $defaultName = 'Untitled'): array
    {
        $payload = $block['payload'] ?? [];
        $name = $this->extractBlockName($block, $defaultName);

        $property = [
            'id' => $this->generateFieldId(),
            'name' => $name,
            'type' => $opnType,
            'required' => (bool) ($payload['isRequired'] ?? false),
            'hidden' => (bool) ($payload['isHidden'] ?? false),
        ];

        if (! empty($payload['placeholder']) && is_string($payload['placeholder'])) {
            $property['placeholder'] = $this->sanitizeText($payload['placeholder'], 255);
        }

        if (($name === $defaultName || $name === '') && ! empty($property['placeholder'])) {
            $property['name'] = $property['placeholder'];
        }

        return $property;
    }

    private function extractBlockName(array $block, string $default = 'Untitled'): string
    {
        $payload = $block['payload'] ?? [];

        foreach (['name', 'title', 'label'] as $key) {
            if (! empty($payload[$key]) && is_string($payload[$key])) {
                return $this->sanitizeText(trim($payload[$key]), 255);
            }
        }

        foreach (['title', 'label'] as $key) {
            if (! empty($block[$key]) && is_string($block[$key])) {
                return $this->sanitizeText(trim($block[$key]), 255);
            }
        }

        return $default;
    }

    private function extractOptionLabels(array $optionBlocks): array
    {
        return array_values(array_filter(
            array_map(fn ($opt) => $this->extractOptionText($opt), $optionBlocks),
            fn ($s) => $s !== ''
        ));
    }

    private function extractOptionText(array $optionBlock): string
    {
        $p = $optionBlock['payload'] ?? [];

        $text = $p['text'] ?? null;
        if (is_string($text) && $text !== '') {
            return $this->sanitizeText($text, 255);
        }

        $html = $this->flattenSafeHTMLSchema($p['html'] ?? $p['safeHTMLSchema'] ?? null);

        return $html !== '' ? $this->sanitizeText($html, 255) : '';
    }

    private function isThankYouPageBreak(array $block): bool
    {
        if (($block['type'] ?? '') !== 'PAGE_BREAK') {
            return false;
        }
        $payload = $block['payload'] ?? [];

        return ! empty($payload['isThankYouPage']) || ! empty($payload['isQualifiedForThankYouPage']);
    }

    private function renderThankYouBlock(array $block): string
    {
        $type = $block['type'] ?? '';
        if (! in_array($type, self::TEXT_TYPES, true)) {
            return '';
        }

        $inner = $this->renderSchemaHtml($block['payload']['safeHTMLSchema'] ?? null);
        if ($inner === '') {
            return '';
        }

        $tag = match ($type) {
            'TITLE', 'HEADING_1' => 'h2',
            'HEADING_2' => 'h3',
            'HEADING_3' => 'h4',
            default => 'p',
        };

        return "<{$tag}>{$inner}</{$tag}>";
    }

    private function renderSchemaHtml(mixed $schema): string
    {
        if (! is_array($schema) || $schema === []) {
            return '';
        }

        $out = '';
        foreach ($schema as $item) {
            $out .= $this->renderSchemaInline($item);
        }

        return $out;
    }

    private function renderSchemaInline(mixed $item): string
    {
        if (is_string($item)) {
            return e($item);
        }

        if (! is_array($item)) {
            return '';
        }

        $first = $item[0] ?? null;
        if (is_string($first)) {
            $text = e($first);
            $attrs = is_array($item[1] ?? null) ? $item[1] : [];
            $href = null;
            foreach ($attrs as $attr) {
                if (is_array($attr) && count($attr) >= 2 && $attr[0] === 'href' && is_string($attr[1])) {
                    $href = $attr[1];
                    break;
                }
            }
            if ($href !== null && $text !== '') {
                return '<a href="' . e($href) . '" target="_blank" rel="noopener">' . $text . '</a>';
            }

            return $text;
        }

        $out = '';
        foreach ($item as $sub) {
            $out .= $this->renderSchemaInline($sub);
        }

        return $out;
    }

    private function flattenSafeHTMLSchema(mixed $node): string
    {
        if (is_string($node)) {
            return $node;
        }
        if (! is_array($node)) {
            return '';
        }

        $out = '';
        foreach ($node as $item) {
            $out .= $this->flattenSafeHTMLSchema($item);
        }

        return $out;
    }

    private function safeHTMLSchemaToContent(mixed $schema): string
    {
        if ($schema === null || $schema === []) {
            return '';
        }

        $plain = trim($this->flattenSafeHTMLSchema($schema));

        return $plain !== '' ? '<p>' . e($this->sanitizeText($plain, 8000)) . '</p>' : '';
    }

    private function addSelectOptions(array $property, array $options): array
    {
        if ($options !== []) {
            $property[$property['type']]['options'] = array_map(
                fn ($label) => ['id' => $this->generateFieldId(), 'name' => $label],
                array_values($options)
            );
        }

        return $property;
    }
}
