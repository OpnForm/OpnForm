<?php

namespace App\Rules;

use App\Models\Forms\Form;
use App\Service\Forms\FormLogicConditionChecker;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CustomFieldValidationRule implements ValidationRule
{
    /**
     * Indicates whether the rule should be implicit.
     *
     * @var bool
     */
    public $implicit = true;

    private bool $errorOccurred = false;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(public array $validation, public array $formData, public ?Form $form = null)
    {
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $logicConditions = $this->validation['error_conditions']['conditions'] ?? null;
        if (empty($logicConditions) || empty($logicConditions['children'] ?? [])) {
            return true;
        }

        try {
            // Prepare form data with form context if form is provided
            $formDataWithContext = $this->formData;
            if ($this->form) {
                $formDataWithContext = array_merge($this->formData, [
                    'form' => [
                        'id' => $this->form->id,
                        'slug' => $this->form->slug,
                    ]
                ]);
            }

            return FormLogicConditionChecker::conditionsMet(
                $logicConditions,
                $formDataWithContext
            );
        } catch (\Exception $e) {
            $this->errorOccurred = true;
            return false;
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->passes($attribute, $value)) {
            $fail($this->message());
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        if ($this->errorOccurred) {
            return 'An validation logic error occurred';
        }
        return isset($this->validation['error_message']) ? $this->validation['error_message'] : 'Invalid input';
    }
}
