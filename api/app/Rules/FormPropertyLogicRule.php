<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class FormPropertyLogicRule implements DataAwareRule, ValidationRule
{
    public const ACTIONS_VALUES = [
        'show-block',
        'hide-block',
        'make-it-optional',
        'require-answer',
        'enable-block',
        'disable-block',
    ];

    private static $conditionMappingData = null;

    public static function getConditionMapping()
    {
        return config('opnform.condition_mapping');
    }

    private $isConditionCorrect = true;

    private $isActionCorrect = true;

    private $conditionErrors = [];

    private $field = [];

    private $data = [];

    private $operator = '';

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
        $this->operator = $operator;

        if (!isset(self::getConditionMapping()[$typeField])) {
            $this->isConditionCorrect = false;
            $this->conditionErrors[] = 'configuration not found for condition type';
            return;
        }

        if (!isset(self::getConditionMapping()[$typeField]['comparators'][$operator])) {
            $this->isConditionCorrect = false;
            $this->conditionErrors[] = 'configuration not found for condition operator';
            return;
        }

        $comparatorDef = self::getConditionMapping()[$typeField]['comparators'][$operator];
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

    private function valueHasCorrectType($type, $value)
    {
        if ($type === 'string' && isset(self::getConditionMapping()[$this->field['type']]['comparators'][$this->operator]['format'])) {
            $format = self::getConditionMapping()[$this->field['type']]['comparators'][$this->operator]['format'];
            if ($format['type'] === 'regex') {
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

    private function checkActions($actions)
    {
        if (is_array($actions) && count($actions) > 0) {
            foreach ($actions as $val) {
                if (
                    !in_array($val, static::ACTIONS_VALUES) ||
                    (in_array($this->field['type'], ['nf-text', 'nf-code', 'nf-page-break', 'nf-divider', 'nf-image', 'nf-video']) && !in_array($val, ['hide-block', 'show-block'])) ||
                    (isset($this->field['hidden']) && $this->field['hidden'] && !in_array($val, ['show-block', 'require-answer'])) ||
                    (isset($this->field['required']) && $this->field['required'] && !in_array($val, ['make-it-optional', 'hide-block', 'disable-block'])) ||
                    (isset($this->field['disabled']) && $this->field['disabled'] && !in_array($val, ['enable-block', 'require-answer', 'make-it-optional']))
                ) {
                    $this->isActionCorrect = false;
                    break;
                }
            }
        } else {
            $this->isActionCorrect = false;
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
        $this->setProperty($attribute);
        if (isset($value['conditions'])) {
            $this->checkConditions($value['conditions']);
            $this->checkActions($value['actions'] ?? null);
        }

        return $this->isConditionCorrect && $this->isActionCorrect;
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
            $message = 'The logic conditions for ' . $this->field['name'] . ' are not complete.';
        } elseif (!$this->isActionCorrect) {
            $message = 'The logic actions for ' . $this->field['name'] . ' are not valid.';
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
        $this->isActionCorrect = true;
        $this->conditionErrors = [];

        return $this;
    }

    private function setProperty(string $attributeKey)
    {
        $attributeKey = Str::of($attributeKey)->replace('.logic', '')->toString();
        $this->field = \Arr::get($this->data, $attributeKey);
    }
}
