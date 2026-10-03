<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeAccountName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            return;
        }

        $name = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Account names are reused in automated emails, including marketing emails.
        if (preg_match('/[\x00-\x1F\x7F<>]|[a-z][a-z0-9+.-]*:\/\/|www\.|[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}(?:\b|\/)/iu', $name)) {
            $fail('Please enter a name without links or HTML.');
        }
    }
}
