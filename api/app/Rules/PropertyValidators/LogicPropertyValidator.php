<?php

namespace App\Rules\PropertyValidators;

/**
 * Validates logic configuration for form properties.
 * Checks that conditions and actions are properly configured.
 */
class LogicPropertyValidator implements PropertyValidatorInterface
{
    public const ACTIONS_VALUES = [
        'show-block',
        'hide-block',
        'make-it-optional',
        'require-answer',
        'enable-block',
        'disable-block',
    ];

    private static ?array $conditionMappingData = null;

    private bool $isConditionCorrect = true;
    private bool $isActionCorrect = true;
    private array $conditionErrors = [];
    private array $field = [];
    private string $operator = '';

    public static function getConditionMapping(): array
    {
        if (self::$conditionMappingData === null) {
            self::$conditionMappingData = config('opnform.condition_mapping');
        }

        return self::$conditionMappingData;
    }

    public function validate(array $property, int $index, array $context): array
    {
        $errors = [];

        // Reset state for this validation
        $this->isConditionCorrect = true;
        $this->isActionCorrect = true;
        $this->conditionErrors = [];
        $this->field = $property;

        $logic = $property['logic'] ?? null;

        // Early bail for empty/null logic
        if (empty($logic) || !is_array($logic)) {
            return $errors;
        }

        // Validate logic is an array (nullable)
        if (!is_array($logic)) {
            $errors['logic'] = 'The logic field must be an array.';
            return $errors;
        }

        // If no conditions, logic is valid (empty logic)
        if (!isset($logic['conditions'])) {
            return $errors;
        }

        // Check conditions
        $this->checkConditions($logic['conditions']);

        // Check actions
        $this->checkActions($logic['actions'] ?? null);

        // Build error message if validation failed
        if (!$this->isConditionCorrect || !$this->isActionCorrect) {
            $errors['logic'] = $this->buildErrorMessage($property['name'] ?? 'Unknown');
        }

        return $errors;
    }

    private function checkBaseCondition(array $condition): void
    {
        if (!isset($condition['value'])) {
            $this->isConditionCorrect = false;
            $this->conditionErrors[] = 'missing condition body';
            return;
        }

        if (!isset($condition['value']['property_meta'])) {
            $this->isConditionCorrect = false;
            $this->conditionErrors[] = 'missing condition property';
            return;
        }

        if (!isset($condition['value']['property_meta']['type'])) {
            $this->isConditionCorrect = false;
            $this->conditionErrors[] = 'missing condition property type';
            return;
        }

        if (!isset($condition['value']['operator'])) {
            $this->isConditionCorrect = false;
            $this->conditionErrors[] = 'missing condition operator';
            return;
        }

        $typeField = $condition['value']['property_meta']['type'];
        $operator = $condition['value']['operator'];
        $this->operator = $operator;

        // Get mapping once for this check
        $mapping = self::getConditionMapping();

        if (!isset($mapping[$typeField])) {
            $this->isConditionCorrect = false;
            $this->conditionErrors[] = 'configuration not found for condition type';
            return;
        }

        if (!isset($mapping[$typeField]['comparators'][$operator])) {
            $this->isConditionCorrect = false;
            $this->conditionErrors[] = 'configuration not found for condition operator';
            return;
        }

        $comparatorDef = $mapping[$typeField]['comparators'][$operator];
        $needsValue = !empty((array)$comparatorDef);

        if ($needsValue && !isset($condition['value']['value'])) {
            $this->isConditionCorrect = false;
            $this->conditionErrors[] = 'missing condition value';
            return;
        }

        if ($needsValue) {
            $type = $comparatorDef['expected_type'] ?? null;
            $value = $condition['value']['value'];

            if (is_array($type)) {
                $foundCorrectType = false;
                foreach ($type as $subtype) {
                    if ($this->valueHasCorrectType($subtype, $value)) {
                        $foundCorrectType = true;
                    }
                }
                if (!$foundCorrectType) {
                    $this->isConditionCorrect = false;
                    $this->conditionErrors[] = 'wrong type of condition value';
                }
            } else {
                if (!$this->valueHasCorrectType($type, $value)) {
                    $this->isConditionCorrect = false;
                    $this->conditionErrors[] = 'wrong type of condition value';
                }
            }
        }
    }

    private function valueHasCorrectType(?string $type, mixed $value): bool
    {
        if ($type === 'string') {
            $mapping = self::getConditionMapping();
            $fieldType = $this->field['type'] ?? null;
            $format = $mapping[$fieldType]['comparators'][$this->operator]['format'] ?? null;
            if ($format && ($format['type'] ?? null) === 'regex') {
                try {
                    preg_match('/' . $value . '/', '');
                    return true;
                } catch (\Exception $e) {
                    $this->conditionErrors[] = 'invalid regex pattern';
                    return false;
                }
            }
        }

        if (
            ($type === 'string' && gettype($value) !== 'string') ||
            ($type === 'boolean' && !is_bool($value)) ||
            ($type === 'number' && !is_numeric($value)) ||
            ($type === 'object' && !is_array($value))
        ) {
            return false;
        }

        return true;
    }

    private function checkConditions(mixed $conditions): void
    {
        if (!is_array($conditions)) {
            $this->isConditionCorrect = false;
            $this->conditionErrors[] = 'conditions must be an array';
            return;
        }

        if (array_key_exists('operatorIdentifier', $conditions)) {
            if (($conditions['operatorIdentifier'] !== 'and') && ($conditions['operatorIdentifier'] !== 'or')) {
                $this->conditionErrors[] = 'missing operator';
                $this->isConditionCorrect = false;
                return;
            }

            if (isset($conditions['operatorIdentifier']['children'])) {
                $this->conditionErrors[] = 'extra condition';
                $this->isConditionCorrect = false;
                return;
            }

            if (!isset($conditions['children']) || !is_array($conditions['children'])) {
                $this->conditionErrors[] = 'wrong sub-condition type';
                $this->isConditionCorrect = false;
                return;
            }

            foreach ($conditions['children'] as $child) {
                $this->checkConditions($child);
            }
        } elseif (isset($conditions['identifier'])) {
            $this->checkBaseCondition($conditions);
        }
    }

    private function checkActions(mixed $actions): void
    {
        if (!is_array($actions) || count($actions) === 0) {
            $this->isActionCorrect = false;
            return;
        }

        $layoutBlocks = ['nf-text', 'nf-code', 'nf-page-break', 'nf-divider', 'nf-image', 'nf-video'];
        $fieldType = $this->field['type'] ?? null;
        $isHidden = $this->field['hidden'] ?? false;
        $isRequired = $this->field['required'] ?? false;
        $isDisabled = $this->field['disabled'] ?? false;

        foreach ($actions as $action) {
            if (
                !in_array($action, self::ACTIONS_VALUES) ||
                (in_array($fieldType, $layoutBlocks) && !in_array($action, ['hide-block', 'show-block'])) ||
                ($isHidden && !in_array($action, ['show-block', 'require-answer'])) ||
                ($isRequired && !in_array($action, ['make-it-optional', 'hide-block', 'disable-block'])) ||
                ($isDisabled && !in_array($action, ['enable-block', 'require-answer', 'make-it-optional']))
            ) {
                $this->isActionCorrect = false;
                break;
            }
        }
    }

    private function buildErrorMessage(string $fieldName): string
    {
        $message = '';

        if (!$this->isConditionCorrect) {
            $message = "The logic conditions for {$fieldName} are not complete.";
        } elseif (!$this->isActionCorrect) {
            $message = "The logic actions for {$fieldName} are not valid.";
        }

        if (count($this->conditionErrors) > 0) {
            $message .= ' Error detail(s): ' . implode(', ', $this->conditionErrors);
        }

        return $message;
    }
}
