<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class SelectOptionsRule implements DataAwareRule, ValidationRule
{
    protected array $data = [];

    protected string $fieldType;

    public function __construct(string $fieldType)
    {
        $this->fieldType = $fieldType;
    }

    /**
     * Set the data under validation.
     *
     * @param  array  $data
     * @return $this
     */
    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string, ?string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Extract property index from attribute path
        // e.g., "properties.0.select.options" -> property index = 0
        preg_match('/properties\.(\d+)\.(?:select|multi_select)\.options/', $attribute, $matches);

        if (!$matches || !isset($matches[1])) {
            return;
        }

        $propertyIndex = (int) $matches[1];
        $properties = $this->data['properties'] ?? [];

        // Check if property exists and has correct type
        if (!isset($properties[$propertyIndex]) || $properties[$propertyIndex]['type'] !== $this->fieldType) {
            return;
        }

        // Validate that value is an array
        if (!is_array($value)) {
            $fail($attribute, 'The options must be an array.');
            return;
        }

        // Validate minimum options count
        if (count($value) < 1) {
            $fail($attribute, 'At least one option is required.');
            return;
        }

        $property = $properties[$propertyIndex];
        $optionDisplayMode = $property['option_display_mode'] ?? 'text_only';

        // Validate each option
        foreach ($value as $index => $option) {
            $optionPath = $attribute . '.' . $index;

            // Validate option name (always required)
            if (empty($option['name'] ?? null)) {
                $fail($optionPath . '.name', 'The option name is required.');
            }

            // Validate option id (always required)
            if (empty($option['id'] ?? null)) {
                $fail($optionPath . '.id', 'The option id is required.');
            }

            // Validate option image based on display mode
            $image = $option['image'] ?? null;

            // Image is required for text_and_image and image_only modes
            if (in_array($optionDisplayMode, ['text_and_image', 'image_only']) && empty($image)) {
                $fail($optionPath . '.image', 'The image field is required for select options.');
            }

            // If image is provided, validate it's a valid URL
            if (!empty($image) && !filter_var($image, FILTER_VALIDATE_URL)) {
                $fail($optionPath . '.image', 'The image must be a valid URL.');
            }
        }
    }
}
