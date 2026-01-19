<?php

namespace App\Rules;

use App\Rules\PropertyValidators\LogicPropertyValidator;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class IntegrationLogicRule implements DataAwareRule, ValidationRule
{
    private $isConditionCorrect = true;

    private $conditionErrors = [];

    private $field = [];

    private $data = [];

    private function checkBaseCondition($condition)
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

        // If operator has no format and no expected_type, it means it doesn't need input
        if (!isset(LogicPropertyValidator::getConditionMapping()[$typeField]['comparators'][$operator]['expected_type'])) {
            return;
        }

        if (!isset($condition['value']['value'])) {
            $this->isConditionCorrect = false;
            $this->conditionErrors[] = 'missing condition value';

            return;
        }

        $value = $condition['value']['value'];

        if (!isset(LogicPropertyValidator::getConditionMapping()[$typeField])) {
            $this->isConditionCorrect = false;
            $this->conditionErrors[] = 'configuration not found for condition type';

            return;
        }

        if (!isset(LogicPropertyValidator::getConditionMapping()[$typeField]['comparators'][$operator])) {
            $this->isConditionCorrect = false;
            $this->conditionErrors[] = 'configuration not found for condition operator';

            return;
        }

        $type = LogicPropertyValidator::getConditionMapping()[$typeField]['comparators'][$operator]['expected_type'];

        if (is_array($type)) {
            $foundCorrectType = false;
            foreach ($type as $subtype) {
                if ($this->valueHasCorrectType($subtype, $value)) {
                    $foundCorrectType = true;
                }
            }
            if (!$foundCorrectType) {
                $this->isConditionCorrect = false;
            }
        } else {
            if (!$this->valueHasCorrectType($type, $value)) {
                $this->isConditionCorrect = false;
                $this->conditionErrors[] = 'wrong type of condition value';
            }
        }
    }

    private function valueHasCorrectType($type, $value)
    {
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

    private function checkConditions($conditions)
    {
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

            if (!is_array($conditions['children'])) {
                $this->conditionErrors[] = 'wrong sub-condition type';
                $this->isConditionCorrect = false;

                return;
            }

            foreach ($conditions['children'] as &$child) {
                $this->checkConditions($child);
            }
        } elseif (isset($conditions['identifier'])) {
            $this->checkBaseCondition($conditions);
        }
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (isset($value)) {
            $this->checkConditions($value);
        }

        return $this->isConditionCorrect;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->passes($attribute, $value)) {
            $fail($this->message());
        }
    }

    /**
     * Get the validation error message.
     */
    public function message()
    {
        $message = null;
        if (!$this->isConditionCorrect) {
            $message = 'The logic conditions are not complete.';
        }
        if (count($this->conditionErrors) > 0) {
            return $message . ' Error detail(s): ' . implode(', ', $this->conditionErrors);
        }

        return $message;
    }

    /**
     * Set the data under validation.
     *
     * @param  array  $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;
        $this->isConditionCorrect = true;
        $this->conditionErrors = [];

        return $this;
    }
}
