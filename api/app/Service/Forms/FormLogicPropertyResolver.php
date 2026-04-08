<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;

class FormLogicPropertyResolver
{
    private $property = [];

    private $formData = [];

    private $logic = false;

    public function __construct(private array $prop, private array $values, private ?Form $form = null)
    {
        $this->property = $prop;
        $this->formData = $values;
        $this->logic = isset($this->property['logic']) ? $this->property['logic'] : false;
    }

    public static function isRequired(array $property, array $values, ?Form $form = null): bool
    {
        return (new self($property, $values, $form))->shouldBeRequired();
    }

    public static function isHidden(array $property, array $values, ?Form $form = null): bool
    {
        return (new self($property, $values, $form))->shouldBeHidden();
    }

    private function evaluateConditions(): bool
    {
        if ($this->form) {
            return FormLogicConditionChecker::conditionsMetWithForm(
                $this->logic['conditions'],
                $this->formData,
                $this->form
            );
        }

        return FormLogicConditionChecker::conditionsMet($this->logic['conditions'], $this->formData);
    }

    public function shouldBeRequired(): bool
    {
        // Default required to false if not set
        $isRequired = $this->property['required'] ?? false;

        if (! $this->logic) {
            return $isRequired;
        }

        $conditionsMet = $this->evaluateConditions();

        // If conditions are met and we have actions
        if ($conditionsMet && !empty($this->logic['actions'])) {
            // If field is required but should be made optional
            if ($isRequired && (in_array('make-it-optional', $this->logic['actions']) || in_array('hide-block', $this->logic['actions']))) {
                return false;
            }
            // If field is not required but should be required
            if (!$isRequired && in_array('require-answer', $this->logic['actions'])) {
                return true;
            }
        }

        return $isRequired;
    }

    public function shouldBeHidden(): bool
    {
        if (! isset($this->property['hidden'])) {
            return false;
        }

        if (! $this->logic) {
            return $this->property['hidden'];
        }

        $conditionsMet = $this->evaluateConditions();
        if ($conditionsMet && $this->property['hidden'] && count($this->logic['actions']) > 0 && in_array('show-block', $this->logic['actions'])) {
            return false;
        } elseif ($conditionsMet && ! $this->property['hidden'] && count($this->logic['actions']) > 0 && in_array('hide-block', $this->logic['actions'])) {
            return true;
        } else {
            return $this->property['hidden'];
        }
    }
}
