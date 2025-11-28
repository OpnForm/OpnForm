<?php

namespace App\Service\AI\Prompts\Form;

/**
 * Optimizes form fields for focused mode presentation.
 *
 * Automatically configures field properties to use focused-mode-specific components:
 * - FocusedSelectorInput for select/multi_select with ≤4 options
 * - FocusedToggleInput for checkbox fields
 *
 * Mirrors the logic from PresentationStyleSwitch.vue to ensure consistency
 * between AI-generated forms and user manual interactions.
 */
class FocusedFormOptimizer
{
    /**
     * Apply focused mode optimizations to form properties.
     *
     * @param array $formData Form data with 'properties' array
     * @param string $mode The presentation mode ('focused' or 'classic')
     * @return array The optimized form data
     */
    public static function optimizeFormProperties(array $formData, string $mode = PresentationRules::MODE_CLASSIC): array
    {
        if ($mode !== PresentationRules::MODE_FOCUSED) {
            return $formData;
        }

        if (!isset($formData['properties']) || !is_array($formData['properties'])) {
            return $formData;
        }

        $formData['properties'] = array_map(
            fn ($field) => self::optimizeField($field),
            $formData['properties']
        );

        return $formData;
    }

    /**
     * Optimize a single field for focused mode.
     *
     * @param array|null $field The field to optimize
     * @return array|null The optimized field
     */
    private static function optimizeField(?array $field): ?array
    {
        if ($field === null || !is_array($field)) {
            return $field;
        }

        $type = $field['type'] ?? null;

        // Optimize select/multi_select fields
        if (in_array($type, ['select', 'multi_select'], true)) {
            self::optimizeSelectField($field);
        }

        // Optimize checkbox fields
        if ($type === 'checkbox') {
            self::optimizeCheckboxField($field);
        }

        return $field;
    }

    /**
     * Optimize select/multi_select field for focused mode.
     *
     * For fields with ≤4 options, enable FocusedSelectorInput by removing
     * conflicting options and ensuring use_focused_selector is not disabled.
     * For fields with >4 options, disable FocusedSelectorInput to use dropdown.
     *
     * @param array $field The field to optimize (modified by reference)
     * @return void
     */
    private static function optimizeSelectField(array &$field): void
    {
        $fieldType = $field['type'] ?? null;
        $options = $field[$fieldType]['options'] ?? [];
        $optionCount = is_array($options) ? count($options) : 0;

        if ($optionCount <= 4) {
            // Use FocusedSelectorInput for small option lists
            // Ensure focused selector is enabled
            if (isset($field['use_focused_selector']) && $field['use_focused_selector'] === false) {
                unset($field['use_focused_selector']);
            }
            // Disable conflicting options
            $field['without_dropdown'] = false;
            $field['allow_creation'] = false;
        } else {
            // Use dropdown for larger option lists
            $field['use_focused_selector'] = false;
            // Preserve existing dropdown behavior
        }
    }

    /**
     * Optimize checkbox field for focused mode.
     *
     * FocusedToggleInput is the default for checkboxes in focused mode.
     * Ensure any explicit disables are removed.
     *
     * @param array $field The field to optimize (modified by reference)
     * @return void
     */
    private static function optimizeCheckboxField(array &$field): void
    {
        // Remove any explicit disable so FocusedToggleInput is used by default
        if (isset($field['use_focused_toggle']) && $field['use_focused_toggle'] === false) {
            unset($field['use_focused_toggle']);
        }
    }
}
