<?php

namespace App\Rules\PropertyValidators;

use App\Service\Forms\FormDateValue;

/** Validate date operands in both display logic and custom validation. */
class DateComparisonPropertyValidator implements PropertyValidatorInterface
{
    public function validate(array $property, int $index, array $context): array
    {
        $references = [];
        foreach ($context['properties'] ?? [] as $reference) {
            if (is_array($reference) && is_string($reference['id'] ?? null)) {
                $references[$reference['id']] = $reference;
            }
        }
        foreach ($context['computed_variables'] ?? [] as $variable) {
            if (is_array($variable) && is_string($variable['id'] ?? null)) {
                $references[$variable['id']] = array_merge($variable, ['type' => 'computed']);
            }
        }
        $errors = [];
        foreach ([
            'logic.conditions' => $property['logic']['conditions'] ?? null,
            'validation.error_conditions.conditions' => $property['validation']['error_conditions']['conditions'] ?? null,
        ] as $path => $conditions) {
            $count = 0;
            $this->validateDates($conditions, $path, $references, $errors, 0, $count);
        }

        return $errors;
    }

    private function validateDates(mixed $condition, string $path, array $references, array &$errors, int $depth, int &$count): void
    {
        if ($condition === null) {
            return;
        }
        if (! is_array($condition) || $depth > 10 || ++$count > 100) {
            $errors[$path] = 'The conditions are malformed or exceed the supported limits.';
            return;
        }
        if (isset($condition['children'])) {
            if (! is_array($condition['children'])) {
                $errors[$path] = 'Condition children must be an array.';
                return;
            }
            foreach ($condition['children'] as $i => $child) {
                $this->validateDates($child, $path.'.children.'.$i, $references, $errors, $depth + 1, $count);
                if ($count > 100) {
                    break;
                }
            }
            return;
        }
        $conditionValue = $condition['value'] ?? null;
        if (! is_array($conditionValue) || ($conditionValue['property_meta']['type'] ?? null) !== 'date'
            || ! in_array($conditionValue['operator'] ?? null, ['equals', 'before', 'after', 'on_or_before', 'on_or_after'], true)) {
            return;
        }
        $value = $conditionValue['value'] ?? null;
        $errorPath = $path.'.value.value';
        if (FormDateValue::timestamp($value) !== null) {
            return;
        }
        if (! is_string($value) || ! preg_match('/\A\s*<span\b([^>]*)>[^<]*<\/span>\s*\z/s', $value, $span)
            || ! preg_match('/\bmention-field-id="([^"]+)"/', $span[1], $mention)) {
            $errors[$errorPath] = 'Choose a valid ISO date or a single date field or variable reference.';
            return;
        }
        $id = html_entity_decode($mention[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $reference = $references[$id] ?? null;
        $isField = ($reference['type'] ?? null) !== 'computed';
        $source = $references[$conditionValue['property_meta']['id'] ?? ''] ?? [];
        if ($reference === null || $id === ($conditionValue['property_meta']['id'] ?? null)
            || ($isField && (($reference['type'] ?? null) !== 'date' || ($reference['date_range'] ?? false) || (bool) ($source['with_time'] ?? false) !== (bool) ($reference['with_time'] ?? false)))
            || (! $isField && in_array($reference['result_type'] ?? 'auto', ['number', 'boolean'], true))) {
            $errors[$errorPath] = 'Choose another existing single date field or a variable returning an ISO date.';
        }
    }
}
