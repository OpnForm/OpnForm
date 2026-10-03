<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MaxPasswordLength implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Bcrypt only uses the first 72 bytes, including for UTF-8 passwords.
        if (is_string($value) && strlen($value) > 72) {
            $fail('The password is too long. Please use a shorter password.');
        }
    }
}
