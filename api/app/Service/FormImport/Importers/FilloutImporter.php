<?php

namespace App\Service\FormImport\Importers;

use App\Service\FormImport\FormImportException;

class FilloutImporter extends AbstractImporter
{
    private const FIELD_MAP = [
        'ShortAnswer' => 'text',
        'LongAnswer' => 'text',
        'EmailInput' => 'email',
        'PhoneNumber' => 'phone_number',
        'NumberInput' => 'number',
        'URLInput' => 'url',
        'DatePicker' => 'date',
        'DateRange' => 'date',
        'Dropdown' => 'select',
        'MultiSelect' => 'multi_select',
        'MultipleChoice' => 'select',
        'Rating' => 'rating',
        'StarRating' => 'rating',
        'Slider' => 'slider',
        'FileUpload' => 'files',
        'Signature' => 'signature',
        'Text' => 'nf-text',
        'Checkbox' => 'checkbox',
        'Checkboxes' => 'multi_select',
        'Switch' => 'checkbox',
        'OpinionScale' => 'scale',
        'Matrix' => 'matrix',
        'PageBreak' => 'nf-page-break',
    ];

    private const SKIP_TYPES = ['Button', 'ThankYou', 'thank_you'];

    public function import(array $importData): array
    {
        $html = $this->fetchHtml($importData['url']);
        $data = $this->extractNextData($html);

        return $this->parseFormData($data);
    }

    public function allowedDomains(): array
    {
        return ['fillout.com', '*.fillout.com'];
    }

    private function parseFormData(array $data): array
    {
        $pageProps = $data['props']['pageProps'] ?? null;

        if (! $pageProps) {
            throw new FormImportException('Could not find form data in the page structure.');
        }

        $title = $this->sanitizeText($pageProps['flow']['name'] ?? 'Imported Fillout Form', 60);

        $template = $pageProps['flowSnapshot']['template'] ?? [];
        $stepsMap = $this->asMap($template['steps'] ?? []);

        ['properties' => $properties, 'settings' => $settings] = $this->mapSteps(
            $stepsMap,
            $template['firstStep'] ?? null
        );

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

    /**
     * Walk the Fillout step graph in presentation order and bucket the output
     * into cover/info widgets, question groups, and a thank-you text blob —
     * then join those pieces once we know whether to use a focused layout.
     *
     * @param  array<string, array>  $stepsMap
     * @return array{properties: array<int, array>, settings: array<string, mixed>}
     */
    private function mapSteps(array $stepsMap, ?string $firstStepId): array
    {
        $coverProps = [];
        $questionGroups = [];
        $submittedText = '';

        foreach ($this->resolveStepOrder($stepsMap, $firstStepId) as $stepId) {
            $step = $stepsMap[$stepId] ?? null;
            if (! is_array($step)) {
                continue;
            }

            switch ($step['type'] ?? 'form') {
                case 'ending':
                case 'thank_you':
                    $submittedText .= $this->extractThankYouHtml($step);
                    break;

                case 'cover':
                    if ($cover = $this->renderCoverContent($step)) {
                        $coverProps[] = $cover;
                    }
                    break;

                default:
                    if ($group = $this->mapStepWidgets($step)) {
                        $questionGroups[] = $group;
                    }
            }
        }

        // Nice-to-have: Fillout forms are frequently page-per-question. When
        // the majority of question steps only hold a single interactive field
        // the classic stacked layout looks awkward, so we propose a focused
        // presentation and let the user tweak from there.
        $totalGroups = count($questionGroups);
        $singleFieldGroups = count(array_filter($questionGroups, fn ($g) => $g['interactive'] <= 1));
        $isFocused = $totalGroups >= 2 && $singleFieldGroups >= (int) ceil($totalGroups * 0.66);

        $properties = $coverProps;
        foreach ($questionGroups as $i => $group) {
            // Focused mode paginates one field at a time, so an explicit
            // nf-page-break between steps would produce empty intermediate
            // pages. Only emit separators in the classic layout.
            if ($i > 0 && ! $isFocused) {
                $properties[] = [
                    'id' => $this->generateFieldId(),
                    'name' => 'Page Break',
                    'type' => 'nf-page-break',
                ];
            }
            array_push($properties, ...$group['props']);
        }

        return [
            'properties' => $properties,
            'settings' => [
                'submitted_text' => $submittedText,
                'presentation_style' => $isFocused ? 'focused' : null,
                'size' => $isFocused ? 'lg' : null,
            ],
        ];
    }

    /**
     * @return array{props: array<int, array>, interactive: int}|null
     */
    private function mapStepWidgets(array $step): ?array
    {
        $props = [];
        $interactive = 0;

        foreach ($this->sortWidgets($step['template']['widgets'] ?? []) as $widget) {
            $mapped = $this->mapWidget($widget);
            if ($mapped === null) {
                continue;
            }
            $props[] = $mapped;
            if (! str_starts_with($mapped['type'] ?? '', 'nf-')) {
                $interactive++;
            }
        }

        return $props === [] ? null : ['props' => $props, 'interactive' => $interactive];
    }

    /**
     * Starting from firstStep, follow step.nextStep / Button widget nextStep
     * to build a presentation-order list. Any steps not reachable (rare; e.g.
     * orphan ending pages) are appended at the end so their content is still
     * considered.
     *
     * @param  array<string, array>  $stepsMap
     * @return array<int, string>
     */
    private function resolveStepOrder(array $stepsMap, ?string $firstStepId): array
    {
        $ordered = [];
        $cursor = $firstStepId && isset($stepsMap[$firstStepId])
            ? $firstStepId
            : array_key_first($stepsMap);

        while (is_string($cursor) && $cursor !== '' && ! in_array($cursor, $ordered, true) && isset($stepsMap[$cursor])) {
            $ordered[] = $cursor;
            $cursor = $this->findNextStepId($stepsMap[$cursor]);
        }

        return array_merge($ordered, array_values(array_diff(array_keys($stepsMap), $ordered)));
    }

    private function findNextStepId(array $step): ?string
    {
        foreach ($this->sortWidgets($step['template']['widgets'] ?? []) as $widget) {
            $next = $widget['template']['nextStep']['defaultNextStep'] ?? null;
            if (($widget['type'] ?? '') === 'Button' && is_string($next) && $next !== '') {
                return $next;
            }
        }

        $stepNext = $step['nextStep']['defaultNextStep'] ?? null;

        return is_string($stepNext) && $stepNext !== '' ? $stepNext : null;
    }

    private function extractThankYouHtml(array $step): string
    {
        $html = '';
        foreach ($this->sortWidgets($step['template']['widgets'] ?? []) as $widget) {
            if (($widget['type'] ?? '') !== 'ThankYou') {
                continue;
            }
            $tpl = $widget['template'] ?? [];
            $html .= $this->stripReferencePlaceholders($this->unwrapString($tpl['richTitleText'] ?? null));
            $html .= $this->stripReferencePlaceholders($this->unwrapString($tpl['richSubtitleText'] ?? null));
        }

        return $html;
    }

    private function renderCoverContent(array $step): ?array
    {
        $tpl = $step['template'] ?? [];
        $title = $this->unwrapString($tpl['title'] ?? null);
        $subtitle = $this->unwrapString($tpl['subtitle'] ?? null);

        $parts = array_filter([
            $title !== '' ? '<h2>' . $this->stripReferencePlaceholders($title) . '</h2>' : null,
            $subtitle !== '' ? $this->stripReferencePlaceholders($subtitle) : null,
        ]);

        if ($parts === []) {
            return null;
        }

        return [
            'id' => $this->generateFieldId(),
            'name' => $this->sanitizeText(strip_tags($title), 255) ?: 'Welcome',
            'type' => 'nf-text',
            'content' => implode('', $parts),
        ];
    }

    /**
     * Fillout stores widgets as either {id: widget} maps or (older snapshots)
     * plain lists. Normalise to a list ordered by position.row.
     *
     * @return array<int, array>
     */
    private function sortWidgets(mixed $widgets): array
    {
        $list = array_values($this->asMap($widgets));
        usort(
            $list,
            fn ($a, $b) => ($a['position']['row'] ?? PHP_INT_MAX) <=> ($b['position']['row'] ?? PHP_INT_MAX)
        );

        return $list;
    }

    /** @return array<string|int, array> */
    private function asMap(mixed $value): array
    {
        return is_array($value) ? array_filter($value, 'is_array') : [];
    }

    /**
     * Unwrap Fillout's {logic: value} or {logic: {value: …}} envelopes.
     * Fields can be static scalars or dynamic references depending on whether
     * the form author used "Show smart values" in the designer.
     */
    private function unwrap(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $logic = $value['logic'] ?? null;

        return is_array($logic) ? ($logic['value'] ?? null) : $logic;
    }

    private function unwrapString(mixed $value): string
    {
        $unwrapped = $this->unwrap($value);

        return is_string($unwrapped) ? $unwrapped : '';
    }

    /**
     * Reference tokens like {{acHmp9Mq…}} render literally in OpnForm's
     * submitted_text. Strip them so the fallback copy stays readable — users
     * can re-add field substitutions manually.
     */
    private function stripReferencePlaceholders(string $html): string
    {
        return preg_replace('/\s*\{\{[^}]+\}\}\s*/', ' ', $html) ?? $html;
    }

    private function mapWidget(array $widget): ?array
    {
        $filloutType = $widget['type'] ?? null;

        if (! $filloutType || in_array($filloutType, self::SKIP_TYPES, true)) {
            return null;
        }

        $template = $widget['template'] ?? [];
        $label = $this->unwrapString($template['label'] ?? null);
        $placeholder = $this->unwrapString($template['placeholder'] ?? null);
        $caption = $this->unwrapString($template['caption'] ?? null);

        $name = $this->sanitizeText($label, 255)
            ?: $this->sanitizeText($placeholder ?: $caption ?: ($widget['name'] ?? ''), 255);

        if ($name === '' || $name === 'Untitled button field') {
            $name = 'Untitled';
        }

        $property = [
            'id' => $this->generateFieldId(),
            'name' => $name,
            'type' => self::FIELD_MAP[$filloutType] ?? 'text',
            'required' => (bool) $this->unwrap($template['required'] ?? null),
            'hidden' => false,
        ];

        if ($placeholder !== '') {
            $property['placeholder'] = $placeholder;
        }

        return $this->applyTypeSpecifics($property, $filloutType, $template, $label);
    }

    private function applyTypeSpecifics(array $property, string $filloutType, array $template, string $label): ?array
    {
        switch ($filloutType) {
            case 'Switch':
                $property['use_toggle_switch'] = true;

                return $property;

            case 'OpinionScale':
                $property['scale_min_value'] = $template['minValue'] ?? 1;
                $property['scale_max_value'] = $template['maxValue'] ?? 5;
                $property['scale_step_value'] = 1;

                return $property;

            case 'DateRange':
                $property['date_range'] = true;

                return $property;

            case 'LongAnswer':
                $property['multi_lines'] = true;

                return $property;

            case 'Matrix':
                $property['rows'] = $this->mapMatrixAxis($template['rows'] ?? [], 'Row');
                $property['columns'] = $this->mapMatrixAxis($template['columns'] ?? [], 'Column');

                return $property;

            case 'MultipleChoice':
            case 'Dropdown':
            case 'MultiSelect':
            case 'Checkboxes':
                $options = $this->extractOptions($template);
                $property = $this->addSelectOptions($property, $options);
                if ($filloutType !== 'Dropdown' && count($options) <= 5) {
                    $property['without_dropdown'] = true;
                }

                return $property;

            case 'Rating':
                $property['rating_max_value'] = $this->unwrap($template['maxRating'] ?? null) ?? 5;

                return $property;

            case 'Slider':
                $property['slider_min_value'] = $this->unwrap($template['minValue'] ?? null) ?? 0;
                $property['slider_max_value'] = $this->unwrap($template['maxValue'] ?? null) ?? 100;
                $property['slider_step_value'] = $this->unwrap($template['step'] ?? null) ?? 1;

                return $property;

            case 'Text':
                $plain = $this->sanitizeText($label, 2000);
                if ($plain === '') {
                    // Empty Text widgets are page-heading placeholders; drop
                    // them so the imported form doesn't render blank banners.
                    return null;
                }
                $property['type'] = 'nf-text';
                $property['content'] = '<p>' . e($plain) . '</p>';
                unset($property['required'], $property['hidden']);

                return $property;
        }

        return $property;
    }

    /** @return array<int, string> */
    private function mapMatrixAxis(array $items, string $fallback): array
    {
        return array_map(
            fn ($item) => $this->sanitizeText($this->unwrapString($item['label'] ?? null) ?: ($item['name'] ?? $fallback)),
            $items
        );
    }

    /** @return array<int, string> */
    private function extractOptions(array $template): array
    {
        $optionsData = $template['options']['staticOptions'] ?? $template['options'] ?? [];

        return array_values(array_filter(array_map(
            fn ($opt) => $this->sanitizeText(
                $this->unwrapString($opt['label'] ?? null) ?: ($opt['value'] ?? $opt['name'] ?? '')
            ),
            $optionsData
        )));
    }

    private function addSelectOptions(array $property, array $options): array
    {
        if ($options !== []) {
            $property[$property['type']]['options'] = array_map(
                fn ($label) => ['id' => $this->generateFieldId(), 'name' => $label],
                $options
            );
        }

        return $property;
    }
}
