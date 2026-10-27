<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidUrl implements ValidationRule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Define the regular expression to match the desired URL patterns
        $pattern = '/^(https?:\/\/)?(www\.)?[\w.-]+\.\w+(:\d+)?(\/[^\s]*)?$/';

        return is_string($value) && preg_match($pattern, $value);
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
        return 'The :attribute format is invalid.';
    }
}
