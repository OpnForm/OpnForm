<?php

namespace App\Rules\PropertyValidators;

/**
 * Validates type-specific property fields based on the property type.
 * Only validates fields that are relevant to each specific type.
 */
class TypePropertyValidator implements PropertyValidatorInterface
{
    /**
     * Type-specific validation rules.
     * Key = property type, Value = array of field => validation config
     */
    private const TYPE_RULES = [
        // Text field rules
        'text' => [
            'multi_lines' => ['type' => 'boolean'],
            'max_char_limit' => ['type' => 'integer', 'min' => 1],
            'show_char_limit' => ['type' => 'boolean'],
            'secret_input' => ['type' => 'boolean'],
        ],

        // Date field rules
        'date' => [
            'with_time' => ['type' => 'boolean'],
            'date_range' => ['type' => 'boolean'],
            'prefill_today' => ['type' => 'boolean'],
            'disable_past_dates' => ['type' => 'boolean'],
            'disable_future_dates' => ['type' => 'boolean'],
        ],

        // Select / Multi-select field rules
        'select' => [
            'allow_creation' => ['type' => 'boolean'],
            'without_dropdown' => ['type' => 'boolean'],
            'min_selection' => ['type' => 'integer', 'min' => 0],
            'max_selection' => ['type' => 'integer', 'min' => 1],
        ],
        'multi_select' => [
            'allow_creation' => ['type' => 'boolean'],
            'without_dropdown' => ['type' => 'boolean'],
            'min_selection' => ['type' => 'integer', 'min' => 0],
            'max_selection' => ['type' => 'integer', 'min' => 1],
        ],

        // File upload rules
        'files' => [
            'max_file_size' => ['type' => 'numeric', 'min' => 1],
            'allowed_file_types' => ['type' => 'nullable'],
        ],

        // Checkbox rules
        'checkbox' => [
            'use_toggle_switch' => ['type' => 'boolean'],
        ],

        // Advanced options (can apply to multiple types)
        'text' => [
            'multi_lines' => ['type' => 'boolean'],
            'max_char_limit' => ['type' => 'integer', 'min' => 1],
            'show_char_limit' => ['type' => 'boolean'],
            'secret_input' => ['type' => 'boolean'],
            'generates_uuid' => ['type' => 'boolean'],
            'generates_auto_increment_id' => ['type' => 'boolean'],
        ],
    ];

    /**
     * Fields that can appear on any input type (not layout blocks)
     */
    private const COMMON_INPUT_RULES = [
        'generates_uuid' => ['type' => 'boolean'],
        'generates_auto_increment_id' => ['type' => 'boolean'],
    ];

    public function validate(array $property, int $index, array $context): array
    {
        $errors = [];
        $type = $property['type'] ?? null;

        if (!$type) {
            return $errors; // Type validation handled by CorePropertyValidator
        }

        // Skip layout blocks (nf-*) - they have no type-specific rules
        if (str_starts_with($type, 'nf-')) {
            return $errors;
        }

        // Get rules for this type
        $typeRules = self::TYPE_RULES[$type] ?? [];

        // Add common input rules for non-layout blocks
        $allRules = array_merge($typeRules, self::COMMON_INPUT_RULES);

        foreach ($allRules as $field => $config) {
            if (!isset($property[$field]) || $property[$field] === null) {
                continue; // Field not present or null, skip validation
            }

            $value = $property[$field];
            $fieldError = $this->validateField($field, $value, $config);

            if ($fieldError !== null) {
                $errors[$field] = $fieldError;
            }
        }

        return $errors;
    }

    /**
     * Validate a single field against its configuration.
     */
    private function validateField(string $field, mixed $value, array $config): ?string
    {
        $type = $config['type'] ?? null;

        switch ($type) {
            case 'boolean':
                if (!$this->isBoolean($value)) {
                    return "The {$field} field must be a boolean.";
                }
                break;

            case 'integer':
                if (!$this->isInteger($value)) {
                    return "The {$field} field must be an integer.";
                }
                if (isset($config['min']) && (int)$value < $config['min']) {
                    return "The {$field} field must be at least {$config['min']}.";
                }
                if (isset($config['max']) && (int)$value > $config['max']) {
                    return "The {$field} field must not be greater than {$config['max']}.";
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    return "The {$field} field must be a number.";
                }
                if (isset($config['min']) && (float)$value < $config['min']) {
                    return "The {$field} field must be at least {$config['min']}.";
                }
                if (isset($config['max']) && (float)$value > $config['max']) {
                    return "The {$field} field must not be greater than {$config['max']}.";
                }
                break;

            case 'nullable':
                // No validation needed - field can be anything
                break;
        }

        return null;
    }

    /**
     * Check if a value is boolean or can be interpreted as boolean.
     */
    private function isBoolean(mixed $value): bool
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1'], true);
    }

    /**
     * Check if a value is an integer or can be interpreted as integer.
     */
    private function isInteger(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
            return true;
        }
        return false;
    }
}
