<?php

namespace App\Rules;

use App\Models\Workspace;
use App\Rules\PropertyValidators\CorePropertyValidator;
use App\Rules\PropertyValidators\LogicPropertyValidator;
use App\Rules\PropertyValidators\PaymentPropertyValidator;
use App\Rules\PropertyValidators\PropertyValidatorInterface;
use App\Rules\PropertyValidators\TypePropertyValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;

/**
 * Single-pass validation rule for form properties.
 * Replaces Laravel's wildcard validation (properties.*) with a more efficient
 * single loop that validates all properties at once.
 *
 * This dramatically improves performance for forms with many properties
 * by avoiding Laravel's validation framework overhead per property.
 */
class FormPropertiesRule implements ValidationRule, ValidatorAwareRule
{
    /**
     * @var PropertyValidatorInterface[]
     */
    private array $validators = [];

    private ?Workspace $workspace;

    private ?Validator $validator = null;

    public function __construct(?Workspace $workspace = null)
    {
        $this->workspace = $workspace;

        // Register validators in order of execution
        $this->validators = [
            new CorePropertyValidator(),
            new TypePropertyValidator(),
            new PaymentPropertyValidator($workspace),
            new LogicPropertyValidator(),
        ];
    }

    /**
     * Set the current validator.
     */
    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;
        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            $fail('Properties must be an array.');
            return;
        }

        $allErrors = [];
        $context = [
            'properties' => $value,
            'workspace' => $this->workspace,
        ];

        // Single pass through all properties
        foreach ($value as $index => $property) {
            if (!is_array($property)) {
                $allErrors["properties.{$index}"] = ["Property at index {$index} must be an array."];
                continue;
            }

            // Run each validator on this property
            foreach ($this->validators as $validator) {
                $propertyErrors = $validator->validate($property, $index, $context);

                // Collect errors with proper attribute keys
                foreach ($propertyErrors as $field => $message) {
                    $errorKey = "properties.{$index}.{$field}";
                    if (!isset($allErrors[$errorKey])) {
                        $allErrors[$errorKey] = [];
                    }
                    $allErrors[$errorKey][] = $message;
                }
            }
        }

        // Add errors directly to the validator's message bag
        // This ensures proper error format matching Laravel's wildcard validation
        if ($this->validator && !empty($allErrors)) {
            foreach ($allErrors as $errorKey => $messages) {
                foreach ($messages as $message) {
                    $this->validator->errors()->add($errorKey, $message);
                }
            }
            // Trigger a generic fail to mark validation as failed
            // The actual errors are already in the message bag
            $fail('One or more properties have validation errors.');
        }
    }
}
