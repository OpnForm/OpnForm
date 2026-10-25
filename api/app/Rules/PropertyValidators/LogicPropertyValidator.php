<?php

namespace App\Rules\PropertyValidators;

use App\Service\Forms\FormRegex;

/**
 * Validates display logic configuration for form properties.
 *
 * Besides validating the condition shape, this validator verifies that every
 * referenced field or computed variable still exists and has the expected
 * type. This keeps the browser editor, API and MCP draft validation aligned.
 */
class LogicPropertyValidator implements PropertyValidatorInterface
{
    public const MAX_CONDITION_DEPTH = 10;

    public const MAX_CONDITION_COUNT = 100;

    public const ACTIONS_VALUES = [
        'show-block',
        'hide-block',
        'make-it-optional',
        'require-answer',
        'enable-block',
        'disable-block',
    ];

    private static ?array $conditionMappingData = null;

    public static function getConditionMapping(): array
    {
        if (self::$conditionMappingData === null) {
            self::$conditionMappingData = config('opnform.condition_mapping');
        }

        return self::$conditionMappingData;
    }

    public function validate(array $property, int $index, array $context): array
    {
        if (! array_key_exists('logic', $property) || $property['logic'] === null || $property['logic'] === []) {
            return [];
        }

        if (! is_array($property['logic'])) {
            return ['logic' => 'The logic configuration must be an object.'];
        }

        $logic = $property['logic'];
        $conditions = $logic['conditions'] ?? null;
        $actions = $logic['actions'] ?? [];

        if ($conditions === null && $actions === []) {
            return [];
        }

        $errors = [];
        $conditionCount = 0;
        $references = $this->buildReferenceMap($context);

        if ($conditions === null) {
            $errors['logic.conditions'] = 'Add at least one condition or remove this logic rule.';
        } else {
            $this->validateCondition(
                $conditions,
                'logic.conditions',
                1,
                $conditionCount,
                $property,
                $references,
                $errors,
            );
        }

        $this->validateActions($actions, $property, $errors);

        return $errors;
    }

    /**
     * @param  array<string, array{type: string, name: string, kind: string}>  $references
     * @param  array<string, string>  $errors
     */
    private function validateCondition(
        mixed $condition,
        string $path,
        int $depth,
        int &$conditionCount,
        array $targetProperty,
        array $references,
        array &$errors,
    ): void {
        if (! is_array($condition)) {
            $errors[$path] = 'The condition must be an object.';

            return;
        }

        $conditionCount++;

        if ($conditionCount > self::MAX_CONDITION_COUNT) {
            $errors[$path] = 'A logic rule cannot contain more than '.self::MAX_CONDITION_COUNT.' conditions.';

            return;
        }

        if ($depth > self::MAX_CONDITION_DEPTH) {
            $errors[$path] = 'Condition groups cannot be nested more than '.self::MAX_CONDITION_DEPTH.' levels.';

            return;
        }

        if (array_key_exists('operatorIdentifier', $condition)) {
            $operator = $condition['operatorIdentifier'];
            if (! in_array($operator, ['and', 'or'], true)) {
                $errors[$path.'.operatorIdentifier'] = 'The condition group operator must be "and" or "or".';
            }

            $children = $condition['children'] ?? null;
            if (! is_array($children) || $children === []) {
                $errors[$path.'.children'] = 'The condition group must contain at least one condition.';

                return;
            }

            foreach ($children as $childIndex => $child) {
                $this->validateCondition(
                    $child,
                    $path.'.children.'.$childIndex,
                    $depth + 1,
                    $conditionCount,
                    $targetProperty,
                    $references,
                    $errors,
                );
            }

            return;
        }

        if (! array_key_exists('identifier', $condition)) {
            $errors[$path] = 'The condition must be a condition group or a field condition.';

            return;
        }

        $this->validateLeafCondition($condition, $path, $targetProperty, $references, $errors);
    }

    /**
     * @param  array<string, array{type: string, name: string, kind: string}>  $references
     * @param  array<string, string>  $errors
     */
    private function validateLeafCondition(
        array $condition,
        string $path,
        array $targetProperty,
        array $references,
        array &$errors,
    ): void {
        $value = $condition['value'] ?? null;
        if (! is_array($value)) {
            $errors[$path.'.value'] = 'The condition body is missing.';

            return;
        }

        $propertyMeta = $value['property_meta'] ?? null;
        if (! is_array($propertyMeta)) {
            $errors[$path.'.value.property_meta'] = 'Choose a field or computed variable for this condition.';

            return;
        }

        $referenceId = $propertyMeta['id'] ?? null;
        if (! is_string($referenceId) || $referenceId === '') {
            $errors[$path.'.value.property_meta.id'] = 'The referenced field or computed variable ID is required.';
        } elseif ($references !== [] && ($targetProperty['id'] ?? null) === $referenceId) {
            $errors[$path.'.value.property_meta.id'] = 'A field logic rule cannot reference the same field.';
        } elseif ($references !== [] && ! isset($references[$referenceId])) {
            $errors[$path.'.value.property_meta.id'] = "The referenced field or computed variable [{$referenceId}] no longer exists.";
        }

        $identifier = $condition['identifier'] ?? null;
        if (! is_string($identifier) || $identifier === '') {
            $errors[$path.'.identifier'] = 'The condition identifier is required.';
        } elseif (is_string($referenceId) && $referenceId !== '' && $identifier !== $referenceId) {
            $errors[$path.'.identifier'] = "The condition identifier must match the referenced item [{$referenceId}].";
        }

        $referenceType = $propertyMeta['type'] ?? null;
        if (! is_string($referenceType) || $referenceType === '') {
            $errors[$path.'.value.property_meta.type'] = 'The referenced field or computed variable type is required.';

            return;
        }

        if (is_string($referenceId) && isset($references[$referenceId]) && $references[$referenceId]['type'] !== $referenceType) {
            $actualType = $references[$referenceId]['type'];
            $errors[$path.'.value.property_meta.type'] = "The reference type must be [{$actualType}] for [{$referenceId}], not [{$referenceType}].";
        }

        $mapping = self::getConditionMapping();
        if (! isset($mapping[$referenceType])) {
            $errors[$path.'.value.property_meta.type'] = "Logic conditions do not support the reference type [{$referenceType}].";

            return;
        }

        $operator = $value['operator'] ?? null;
        if (! is_string($operator) || $operator === '') {
            $errors[$path.'.value.operator'] = 'Choose an operator for this condition.';

            return;
        }

        $comparator = $mapping[$referenceType]['comparators'][$operator] ?? null;
        if ($comparator === null) {
            $errors[$path.'.value.operator'] = "The operator [{$operator}] is not available for [{$referenceType}] conditions.";

            return;
        }

        if (($comparator['custom_validation_only'] ?? false) === true) {
            $errors[$path.'.value.operator'] = "The operator [{$operator}] is only available for custom validation rules.";

            return;
        }

        $needsValue = $comparator !== [];
        if ($needsValue && ! array_key_exists('value', $value)) {
            $errors[$path.'.value.value'] = "The operator [{$operator}] requires a comparison value.";

            return;
        }

        if ($needsValue && ! $this->valueHasCorrectType($comparator, $value['value'])) {
            $expectedType = $comparator['expected_type'] ?? 'the expected';
            $expectedType = is_array($expectedType) ? implode(' or ', $expectedType) : $expectedType;
            $errors[$path.'.value.value'] = "The comparison value for [{$operator}] must be {$expectedType}.";
        }
    }

    private function valueHasCorrectType(array $comparator, mixed $value): bool
    {
        if (is_string($value) && str_contains($value, 'mention-field-id')) {
            return true;
        }

        $expectedTypes = (array) ($comparator['expected_type'] ?? []);
        if ($expectedTypes === []) {
            return true;
        }

        foreach ($expectedTypes as $expectedType) {
            $matches = match ($expectedType) {
                'string' => is_string($value),
                'boolean' => is_bool($value),
                'number' => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)),
                'object' => is_array($value),
                default => true,
            };

            if (! $matches) {
                continue;
            }

            if (
                $expectedType === 'string'
                && ($comparator['format']['type'] ?? null) === 'regex'
                && ! FormRegex::isValid($value)
            ) {
                continue;
            }

            return true;
        }

        return false;
    }

    /** @param array<string, string> $errors */
    private function validateActions(mixed $actions, array $property, array &$errors): void
    {
        if (! is_array($actions) || $actions === []) {
            $errors['logic.actions'] = 'Choose at least one action for this logic rule.';

            return;
        }

        $allowedActions = self::allowedActionsFor($property);

        foreach ($actions as $actionIndex => $action) {
            $isValid = is_string($action)
                && in_array($action, self::ACTIONS_VALUES, true)
                && in_array($action, $allowedActions, true);

            if (! $isValid) {
                $actionLabel = is_scalar($action) ? (string) $action : 'invalid action';
                $errors['logic.actions.'.$actionIndex] = "The action [{$actionLabel}] is not valid for this field.";
            }
        }
    }

    /** @return array<int, string> */
    public static function allowedActionsFor(array $property): array
    {
        $layoutBlocks = ['nf-text', 'nf-code', 'nf-page-break', 'nf-divider', 'nf-image', 'nf-video', 'nf-audio'];
        $fieldType = $property['type'] ?? null;
        $isHidden = $property['hidden'] ?? false;
        $isRequired = $property['required'] ?? false;
        $isDisabled = $property['disabled'] ?? false;

        return match (true) {
            in_array($fieldType, $layoutBlocks, true) && $isHidden => ['show-block'],
            in_array($fieldType, $layoutBlocks, true) => ['hide-block'],
            $isHidden => ['show-block', 'require-answer'],
            $isDisabled && $isRequired => ['enable-block', 'make-it-optional'],
            $isDisabled => ['enable-block', 'require-answer'],
            $isRequired => ['hide-block', 'disable-block', 'make-it-optional'],
            default => ['hide-block', 'disable-block', 'require-answer'],
        };
    }

    /**
     * @return array<string, array{type: string, name: string, kind: string}>
     */
    private function buildReferenceMap(array $context): array
    {
        $references = [];

        foreach ($context['properties'] ?? [] as $property) {
            if (! is_array($property) || ! is_string($property['id'] ?? null) || ! is_string($property['type'] ?? null)) {
                continue;
            }

            $references[$property['id']] = [
                'type' => $property['type'],
                'name' => is_string($property['name'] ?? null) ? $property['name'] : $property['id'],
                'kind' => 'field',
            ];
        }

        foreach ($context['computed_variables'] ?? [] as $variable) {
            if (! is_array($variable) || ! is_string($variable['id'] ?? null)) {
                continue;
            }

            $references[$variable['id']] = [
                'type' => 'computed',
                'name' => is_string($variable['name'] ?? null) ? $variable['name'] : $variable['id'],
                'kind' => 'computed_variable',
            ];
        }

        return $references;
    }
}
