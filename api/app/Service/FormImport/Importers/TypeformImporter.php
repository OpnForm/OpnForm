<?php

namespace App\Service\FormImport\Importers;

use App\Service\FormImport\FormImportException;

class TypeformImporter extends AbstractImporter
{
    private const FIELD_MAP = [
        'short_text' => 'text',
        'long_text' => 'text',
        'email' => 'email',
        'phone_number' => 'phone_number',
        'number' => 'number',
        'url' => 'url',
        'website' => 'url',
        'date' => 'date',
        'dropdown' => 'select',
        'rating' => 'rating',
        'opinion_scale' => 'scale',
        'nps' => 'scale',
        'yes_no' => 'checkbox',
        'legal' => 'checkbox',
        'checkbox' => 'checkbox',
        'file_upload' => 'files',
        'signature' => 'signature',
        'multiple_choice' => 'select',
        'picture_choice' => 'select',
        'ranking' => 'select',
        'statement' => 'nf-text',
    ];

    private const COMPOSITE_TYPES = ['contact_info', 'address', 'group', 'inline_group'];

    /**
     * Typeform appends a generic "Thanks for completing this typeform…"
     * screen to every form. We don't want that in imported submitted_text.
     */
    private const DEFAULT_THANKYOU_REFS = ['default_tys'];

    private const DEFAULT_THANKYOU_IDS = ['DefaultTyScreen'];

    public function import(array $importData): array
    {
        $url = $importData['url'];
        $this->validateFormUrl($url);

        $html = $this->fetchHtml($url);
        $formData = $this->extractFormData($html);

        $title = $this->sanitizeText($formData['title'] ?? 'Imported Typeform', 60);
        $properties = [];
        if ($welcome = $this->renderWelcomeScreen($formData['welcome_screens'] ?? [])) {
            $properties[] = $welcome;
        }
        array_push($properties, ...$this->mapFields($formData['fields'] ?? []));

        return array_filter([
            'title' => $title,
            'properties' => $properties,
            'presentation_style' => 'focused',
            'size' => 'lg',
            'submitted_text' => $this->renderThankYouScreens($formData['thankyou_screens'] ?? []),
            'settings' => [
                'navigation_arrows' => true,
            ],
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    public function allowedDomains(): array
    {
        return ['*.typeform.com'];
    }

    private function validateFormUrl(string $url): void
    {
        if (! preg_match('#typeform\.com/to/[a-zA-Z0-9]+#', $url)) {
            throw new FormImportException(
                'Could not extract form ID from Typeform URL. Expected format: https://yourname.typeform.com/to/FORM_ID'
            );
        }
    }

    /**
     * Typeform embeds form data in window.rendererData.form on public pages.
     */
    private function extractFormData(string $html): array
    {
        $rdPos = strpos($html, 'window.rendererData');
        if ($rdPos === false) {
            throw new FormImportException(
                'Could not find form data in the page. Make sure the Typeform URL is public and the form is published.'
            );
        }

        return $this->extractRendererFormData($html, $rdPos);
    }

    private function extractRendererFormData(string $html, int $rdPos): array
    {
        $searchRegion = substr($html, $rdPos, 100_000);

        if (! preg_match('/\bform\s*:\s*\{/', $searchRegion, $match, PREG_OFFSET_CAPTURE)) {
            throw new FormImportException(
                'Found renderer data but could not locate form definition.'
            );
        }

        $braceOffset = strpos($searchRegion, '{', $match[0][1]);
        $braceStart = $rdPos + $braceOffset;
        $json = $this->extractBracedBlock($html, $braceStart);

        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new FormImportException('Failed to parse Typeform form data from page.');
        }

        return $data;
    }

    /**
     * Extract a balanced { … } block from $html starting at $start,
     * respecting single- and double-quoted strings.
     */
    private function extractBracedBlock(string $html, int $start): string
    {
        $depth = 0;
        $len = strlen($html);
        $inString = false;
        $stringChar = null;
        $escape = false;

        for ($i = $start; $i < $len; $i++) {
            $c = $html[$i];

            if ($escape) {
                $escape = false;

                continue;
            }

            if ($c === '\\' && $inString) {
                $escape = true;

                continue;
            }

            if (! $inString && ($c === '"' || $c === "'")) {
                $inString = true;
                $stringChar = $c;

                continue;
            }

            if ($inString && $c === $stringChar) {
                $inString = false;

                continue;
            }

            if (! $inString) {
                if ($c === '{') {
                    $depth++;
                } elseif ($c === '}') {
                    $depth--;
                    if ($depth === 0) {
                        return substr($html, $start, $i - $start + 1);
                    }
                }
            }
        }

        throw new FormImportException(
            'Could not find form data in the page. Make sure the Typeform URL is public and the form is published.'
        );
    }

    /**
     * Turn the first welcome screen into an nf-text block that users can edit
     * or remove. We only emit the first screen because OpnForm currently
     * supports a single cover block.
     *
     * @param  array<int, array>  $screens
     */
    private function renderWelcomeScreen(array $screens): ?array
    {
        $screen = $screens[0] ?? null;
        if (! is_array($screen)) {
            return null;
        }

        $title = $this->sanitizeText($screen['title'] ?? '', 2000);
        if ($title === '') {
            return null;
        }

        return [
            'id' => $this->generateFieldId(),
            'name' => $this->sanitizeText(strip_tags($title), 255) ?: 'Welcome',
            'type' => 'nf-text',
            'content' => '<p>' . nl2br(e($title)) . '</p>',
        ];
    }

    /**
     * Combine author-defined thank-you screens into a single submitted_text
     * blob. The default screen appended by Typeform (ref: default_tys) is
     * skipped so imports don't inherit generic copy.
     *
     * @param  array<int, array>  $screens
     */
    private function renderThankYouScreens(array $screens): string
    {
        $parts = [];
        foreach ($screens as $screen) {
            if (! is_array($screen)) {
                continue;
            }
            if (
                in_array($screen['ref'] ?? '', self::DEFAULT_THANKYOU_REFS, true)
                || in_array($screen['id'] ?? '', self::DEFAULT_THANKYOU_IDS, true)
            ) {
                continue;
            }
            $title = $this->sanitizeText($screen['title'] ?? '', 2000);
            if ($title === '') {
                continue;
            }
            $parts[] = '<p>' . nl2br(e($title)) . '</p>';
        }

        return implode('', $parts);
    }

    private function mapFields(array $fields): array
    {
        $properties = [];

        foreach ($fields as $field) {
            $type = $field['type'] ?? 'short_text';

            if (in_array($type, self::COMPOSITE_TYPES)) {
                array_push($properties, ...$this->flattenCompositeField($field));
            } else {
                $mapped = $this->mapSingleField($field);
                if ($mapped) {
                    $properties[] = $mapped;
                }
            }
        }

        return $properties;
    }

    /**
     * Flatten composite Typeform types (contact_info, address, group, inline_group)
     * into individual OpnForm fields.
     */
    private function flattenCompositeField(array $field): array
    {
        $type = $field['type'];
        $subFields = $field['properties']['fields'] ?? [];
        $properties = [];

        foreach ($subFields as $subField) {
            $subType = $subField['type'] ?? 'short_text';

            if (in_array($subType, self::COMPOSITE_TYPES)) {
                array_push($properties, ...$this->flattenCompositeField($subField));
            } else {
                $mapped = $this->mapSingleField($subField);
                if ($mapped) {
                    $properties[] = $mapped;
                }
            }
        }

        return $properties;
    }

    private function mapSingleField(array $field): ?array
    {
        $typeformType = $field['type'] ?? 'short_text';
        $opnType = self::FIELD_MAP[$typeformType] ?? 'text';

        $property = [
            'id' => $this->generateFieldId(),
            'name' => $this->sanitizeText($field['title'] ?? 'Untitled', 255),
            'type' => $opnType,
            'required' => $field['validations']['required'] ?? false,
            'hidden' => false,
        ];

        switch ($typeformType) {
            case 'long_text':
                $property['multi_lines'] = true;
                break;

            case 'multiple_choice':
            case 'picture_choice':
                $choices = $this->extractChoices($field);
                $allowMultiple = $field['properties']['allow_multiple_selection'] ?? false;

                $property['type'] = $allowMultiple ? 'multi_select' : 'select';
                $property = $this->addSelectOptions($property, $choices);
                if (count($choices) <= 5) {
                    $property['without_dropdown'] = true;
                } else {
                    $property['use_focused_selector'] = false;
                }
                break;

            case 'dropdown':
            case 'ranking':
                $choices = $this->extractChoices($field);
                $property = $this->addSelectOptions($property, $choices);
                if (count($choices) <= 5) {
                    $property['without_dropdown'] = true;
                } else {
                    $property['use_focused_selector'] = false;
                }
                break;

            case 'rating':
                $property['rating_max_value'] = $field['properties']['steps'] ?? 5;
                break;

            case 'opinion_scale':
                $property['type'] = 'scale';
                $property['scale_min_value'] = ($field['properties']['start_at_one'] ?? false) ? 1 : 0;
                $property['scale_max_value'] = $field['properties']['steps'] ?? 10;
                $property['scale_step_value'] = 1;
                break;

            case 'nps':
                $property['type'] = 'scale';
                $property['scale_min_value'] = 0;
                $property['scale_max_value'] = ($field['properties']['steps'] ?? 11) - 1;
                $property['scale_step_value'] = 1;
                break;

            case 'legal':
            case 'yes_no':
                $property['type'] = 'checkbox';
                $property['use_toggle_switch'] = true;
                break;

            case 'checkbox':
                $property['type'] = 'checkbox';
                $choices = $this->extractChoices($field);
                if (!empty($choices)) {
                    $property['name'] = $this->sanitizeText($choices[0], 255);
                }
                break;

            case 'statement':
                $property['type'] = 'nf-text';
                $property['content'] = '<p>' . e($this->sanitizeText($field['title'] ?? '', 2000)) . '</p>';
                unset($property['required'], $property['hidden']);
                break;
        }

        return $property;
    }

    private function extractChoices(array $field): array
    {
        $choices = $field['properties']['choices'] ?? [];

        return array_map(
            fn ($choice) => $this->sanitizeText($choice['label'] ?? ''),
            $choices
        );
    }

    private function addSelectOptions(array $property, array $choices): array
    {
        if (!empty($choices)) {
            $property[$property['type']]['options'] = array_map(
                fn ($label) => ['id' => $this->generateFieldId(), 'name' => $label],
                $choices
            );
        }

        return $property;
    }
}
