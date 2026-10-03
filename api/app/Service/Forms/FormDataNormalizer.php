<?php

namespace App\Service\Forms;

use App\Rules\ComputedVariablesRule;
use App\Rules\PropertyValidators\LogicPropertyValidator;
use App\Rules\PropertyValidators\TypePropertyValidator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Stevebauman\Purify\Facades\Purify;

class FormDataNormalizer
{
    private const PROPERTY_BOOLEAN_FIELDS = [
        'hidden',
        'required',
        'disabled',
        'multiple',
        'use_toggle_switch',
        'multi_lines',
        'show_char_limit',
        'secret_input',
        'with_time',
        'date_range',
        'prefill_today',
        'disable_past_dates',
        'disable_future_dates',
        'allow_creation',
        'without_dropdown',
        'generates_uuid',
        'generates_auto_increment_id',
        'hide_field_name',
        'shuffle_options',
        'use_focused_selector',
        'file_upload',
        'camera_upload',
    ];

    /**
     * Normalize data shared by HTTP requests, MCP tools, and draft claims.
     */
    public function normalize(array $data, bool $backfillPropertyIds = true): array
    {
        if (isset($data['title']) && is_string($data['title'])) {
            $data['title'] = Str::substr(trim($data['title']), 0, 255);
        }

        if (isset($data['properties']) && is_array($data['properties'])) {
            $data = $this->migrateObsoleteNonFields($data);
            $data['properties'] = $this->normalizeProperties($data['properties'], $backfillPropertyIds);

            if (array_key_exists('computed_variables', $data)) {
                $computedVariables = is_array($data['computed_variables'])
                    ? array_values($data['computed_variables'])
                    : [];
                $data['computed_variables'] = $this->normalizeComputedVariables(
                    $computedVariables,
                    $data['properties'],
                );
            }

            $data['properties'] = $this->normalizeRecoverableLogic(
                $data['properties'],
                is_array($data['computed_variables'] ?? null) ? $data['computed_variables'] : [],
            );
        }

        return $data;
    }

    private function migrateObsoleteNonFields(array $data): array
    {
        foreach ($data['properties'] as $property) {
            if (! is_array($property) || ! is_string($property['type'] ?? null)) {
                continue;
            }

            if (in_array($property['type'], ['captcha', 'use_captcha'], true)) {
                $data['use_captcha'] = true;
            }

            if (! in_array($property['type'], ['submit', 'submit_button', 'nf-submit'], true)
                || (is_string($data['submit_button_text'] ?? null) && trim($data['submit_button_text']) !== '')) {
                continue;
            }

            foreach (['submit_button_text', 'button_text'] as $field) {
                if (is_string($property[$field] ?? null) && trim($property[$field]) !== '') {
                    $data['submit_button_text'] = Str::substr(trim($property[$field]), 0, 50);

                    break;
                }
            }
        }

        return $data;
    }

    public function normalizeProperties(array $properties, bool $backfillIds = true): array
    {
        $aliases = AgentFormFieldCatalog::normalizationAliases();

        return collect($properties)
            ->filter(fn ($property) => is_array($property))
            ->reject(fn (array $property) => is_string($property['type'] ?? null)
                && in_array($property['type'], AgentFormFieldCatalog::OBSOLETE_NON_FIELD_TYPES, true))
            ->map(function (array $property) use ($aliases, $backfillIds) {
                $propertyType = $property['type'] ?? null;
                if (is_string($propertyType) && isset($aliases[$propertyType])) {
                    if ($propertyType === 'html'
                        && ! isset($property['content'])
                        && is_string($property['html_content'] ?? null)) {
                        $property['content'] = $property['html_content'];
                    }

                    $property = array_replace($property, $aliases[$propertyType]);
                }

                if ($backfillIds
                && (! array_key_exists('id', $property) || $property['id'] === null || $property['id'] === '')) {
                    $property['id'] = Str::uuid()->toString();
                }

                if (isset($property['name']) && is_string($property['name'])) {
                    $property['name'] = Str::substr(trim(strip_tags($property['name'])), 0, 500);
                }

                if (isset($property['help']) && is_string($property['help'])) {
                    $property['help'] = Purify::clean($property['help']);

                    if (strip_tags($property['help']) === '') {
                        $property['help'] = null;
                    }
                }

                foreach (self::PROPERTY_BOOLEAN_FIELDS as $field) {
                    if (! array_key_exists($field, $property)) {
                        continue;
                    }

                    if (in_array($property[$field], [0, 1, '0', '1'], true)) {
                        $property[$field] = (bool) (int) $property[$field];
                    } elseif (is_string($property[$field])
                        && in_array(Str::lower($property[$field]), ['true', 'false'], true)) {
                        $property[$field] = Str::lower($property[$field]) === 'true';
                    } elseif ($property[$field] !== null && ! is_bool($property[$field])) {
                        unset($property[$field]);
                    }
                }

                $property = $this->normalizeOptionalPropertySettings($property);
                $property = $this->normalizeTypeSpecificSettings($property);

                return $this->normalizeSelectOptions($property);
            })
            ->values()
            ->all();
    }

    private function normalizeOptionalPropertySettings(array $property): array
    {
        if (isset($property['width'])
            && ! in_array($property['width'], ['full', '1/2', '1/3', '2/3', '3/4', '1/4'], true)) {
            $property['width'] = 'full';
        }

        if (isset($property['help_position'])
            && ! in_array($property['help_position'], ['below_input', 'above_input'], true)) {
            unset($property['help_position']);
        }

        if (isset($property['align'])
            && ! in_array($property['align'], ['left', 'center', 'right', 'justify'], true)) {
            unset($property['align']);
        }

        if (! array_key_exists('image', $property) || $property['image'] === null) {
            return $property;
        }

        if (! is_array($property['image'])) {
            unset($property['image']);

            return $property;
        }

        $image = $property['image'];
        if (isset($image['url'])
            && (! is_string($image['url']) || filter_var($image['url'], FILTER_VALIDATE_URL) === false)) {
            unset($property['image']);

            return $property;
        }

        if (isset($image['alt'])) {
            if (is_string($image['alt'])) {
                $image['alt'] = Str::substr($image['alt'], 0, 125);
            } else {
                unset($image['alt']);
            }
        }

        if (isset($image['layout'])
            && ! in_array($image['layout'], ['between', 'left-small', 'right-small', 'left-split', 'right-split', 'background'], true)) {
            unset($image['layout']);
        }

        if (isset($image['focal_point']) && ! is_array($image['focal_point'])) {
            unset($image['focal_point']);
        } elseif (isset($image['focal_point'])) {
            foreach (['x', 'y'] as $axis) {
                $value = $image['focal_point'][$axis] ?? null;
                if ($value !== null && (! is_numeric($value) || $value < 0 || $value > 100)) {
                    unset($image['focal_point'][$axis]);
                }
            }

            if ($image['focal_point'] === []) {
                unset($image['focal_point']);
            }
        }

        if (isset($image['brightness'])) {
            $brightness = $image['brightness'];
            if ((! is_int($brightness) && (! is_string($brightness) || preg_match('/^-?\d+$/', $brightness) !== 1))
                || (int) $brightness < -100
                || (int) $brightness > 100) {
                unset($image['brightness']);
            }
        }

        if (isset($image['fade']) && ! is_bool($image['fade'])) {
            unset($image['fade']);
        }

        $property['image'] = $image;

        return $property;
    }

    private function normalizeTypeSpecificSettings(array $property): array
    {
        $errors = (new TypePropertyValidator())->validate($property, 0, []);

        foreach (array_keys($errors) as $field) {
            if ($field !== 'type') {
                unset($property[$field]);
            }
        }

        return $property;
    }

    private function normalizeSelectOptions(array $property): array
    {
        $type = $property['type'] ?? null;

        if (! in_array($type, ['select', 'multi_select'], true)) {
            return $property;
        }

        $validDisplayModes = ['text_only', 'text_and_image', 'image_only'];
        if (! in_array($property['option_display_mode'] ?? 'text_only', $validDisplayModes, true)) {
            $property['option_display_mode'] = 'text_only';
        }

        if (isset($property['option_image_size'])
            && ! in_array($property['option_image_size'], ['sm', 'md', 'lg'], true)) {
            unset($property['option_image_size']);
        }

        if (! isset($property[$type]['options']) || ! is_array($property[$type]['options'])) {
            if (($property['allow_creation'] ?? false) === true) {
                $property[$type] = is_array($property[$type] ?? null) ? $property[$type] : [];
                $property[$type]['options'] = [];
                $property['option_display_mode'] = 'text_only';
            }

            return $property;
        }

        $property[$type]['options'] = collect($property[$type]['options'])
            ->map(function ($option) {
                if (is_array($option) && is_string($option['name'] ?? null)) {
                    $option['name'] = trim($option['name']);
                }

                return $option;
            })
            ->filter(fn ($option) => is_array($option)
                && is_string($option['name'] ?? null)
                && $option['name'] !== '')
            ->map(function (array $option) {
                if (! is_string($option['id'] ?? null) || $option['id'] === '') {
                    $option['id'] = $option['name'];
                }

                if (array_key_exists('image', $option)
                    && (! is_string($option['image'])
                        || filter_var($option['image'], FILTER_VALIDATE_URL) === false)) {
                    unset($option['image']);
                }

                return $option;
            })
            ->values()
            ->all();

        if (in_array($property['option_display_mode'] ?? 'text_only', ['text_and_image', 'image_only'], true)) {
            $hasUnusableImage = collect($property[$type]['options'])->contains(
                fn (array $option) => ! is_string($option['image'] ?? null)
                    || filter_var($option['image'], FILTER_VALIDATE_URL) === false,
            );

            if ($hasUnusableImage) {
                $property['option_display_mode'] = 'text_only';
            }
        }

        return $property;
    }

    private function normalizeRecoverableLogic(array $properties, array $computedVariables): array
    {
        $references = $this->buildReferenceMap($properties, $computedVariables);
        $context = [
            'properties' => $properties,
            'computed_variables' => $computedVariables,
        ];
        $validator = new LogicPropertyValidator();

        foreach ($properties as $index => &$property) {
            if (! is_array($property) || ! array_key_exists('logic', $property)) {
                continue;
            }

            if (! is_array($property['logic'])) {
                unset($property['logic']);

                continue;
            }

            $conditions = $property['logic']['conditions'] ?? null;
            $actions = $property['logic']['actions'] ?? [];
            if ($conditions === null && $actions === []) {
                unset($property['logic']);

                continue;
            }

            if (array_key_exists('conditions', $property['logic'])) {
                $this->repairConditionReferences(
                    $property['logic']['conditions'],
                    $property['id'] ?? null,
                    $references,
                );
            }

            if (is_array($property['logic']['actions'] ?? null)) {
                $allowedActions = LogicPropertyValidator::allowedActionsFor($property);
                $property['logic']['actions'] = collect($property['logic']['actions'])
                    ->filter(fn ($action) => is_string($action) && in_array($action, $allowedActions, true))
                    ->unique()
                    ->values()
                    ->all();
            }

            if ($validator->validate($property, $index, $context) !== []) {
                unset($property['logic']);
            }
        }
        unset($property);

        return $properties;
    }

    /** @param array<string, array{type: string}> $references */
    private function repairConditionReferences(
        mixed &$condition,
        mixed $targetId,
        array $references,
        int $depth = 1,
    ): void {
        if (! is_array($condition) || $depth > LogicPropertyValidator::MAX_CONDITION_DEPTH) {
            return;
        }

        if (array_key_exists('operatorIdentifier', $condition)) {
            if (! is_array($condition['children'] ?? null)) {
                return;
            }

            foreach ($condition['children'] as &$child) {
                $this->repairConditionReferences($child, $targetId, $references, $depth + 1);
            }
            unset($child);

            return;
        }

        $referenceId = $condition['value']['property_meta']['id'] ?? null;
        if (! is_string($referenceId) || $referenceId === '' || $referenceId === $targetId || ! isset($references[$referenceId])) {
            return;
        }

        $condition['identifier'] = $referenceId;
        $condition['value']['property_meta']['type'] = $references[$referenceId]['type'];

        if ($references[$referenceId]['type'] !== 'checkbox') {
            return;
        }

        $condition['value']['operator'] = match ($condition['value']['operator'] ?? null) {
            'equals' => 'is_checked',
            'does_not_equal' => 'is_not_checked',
            default => $condition['value']['operator'] ?? null,
        };
        if (in_array($condition['value']['operator'], ['is_checked', 'is_not_checked'], true)) {
            unset($condition['value']['value']);
        }
    }

    /** @return array<string, array{type: string}> */
    private function buildReferenceMap(array $properties, array $computedVariables): array
    {
        $references = [];

        foreach ($properties as $property) {
            if (is_array($property) && is_string($property['id'] ?? null) && is_string($property['type'] ?? null)) {
                $references[$property['id']] = ['type' => $property['type']];
            }
        }

        foreach ($computedVariables as $variable) {
            if (is_array($variable) && is_string($variable['id'] ?? null)) {
                $references[$variable['id']] = ['type' => 'computed'];
            }
        }

        return $references;
    }

    private function normalizeComputedVariables(array $variables, array $properties): array
    {
        $variables = collect(array_slice($variables, 0, ComputedVariablesRule::MAX_VARIABLE_COUNT))
            ->map(function ($variable) {
                if (! is_array($variable)) {
                    return $variable;
                }

                foreach (['name', 'formula'] as $field) {
                    if (is_string($variable[$field] ?? null)) {
                        $variable[$field] = trim($variable[$field]);
                    }
                }

                return $variable;
            })
            ->values()
            ->all();

        do {
            $invalidIndexes = $this->invalidComputedVariableIndexes($variables, $properties);
            if ($invalidIndexes === []) {
                return $variables;
            }

            foreach ($invalidIndexes as $index) {
                unset($variables[$index]);
            }

            $variables = array_values($variables);
        } while (true);
    }

    /** @return array<int, int> */
    private function invalidComputedVariableIndexes(array $variables, array $properties): array
    {
        $validator = Validator::make([
            'properties' => $properties,
            'computed_variables' => $variables,
        ], [
            'computed_variables' => ['nullable', 'array', new ComputedVariablesRule()],
        ]);
        $validator->passes();

        return collect($validator->errors()->keys())
            ->map(function (string $path): ?int {
                return preg_match('/^computed_variables\.(\d+)(?:\.|$)/', $path, $matches) === 1
                    ? (int) $matches[1]
                    : null;
            })
            ->filter(fn (?int $index) => $index !== null)
            ->unique()
            ->values()
            ->all();
    }
}
