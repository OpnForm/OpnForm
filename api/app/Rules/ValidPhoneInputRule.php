<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPhoneInputRule implements ValidationRule
{
    public \libphonenumber\ValidationResult|int|null $reason = 0;

    public function passes($attribute, $value)
    {
        if (! is_string($value) || ! $value) {
            return false;
        }
        try {
            if (ctype_alpha(substr($value, 0, 2))) {  // First 2 will be country code
                $value = substr($value, 2);
            }
            $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
            $phone = $phoneUtil->parse($value);
            $this->reason = $phoneUtil->isPossibleNumberWithReason($phone);

            return $this->reason === \libphonenumber\ValidationResult::IS_POSSIBLE;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->passes($attribute, $value)) {
            $fail($this->message());
        }
    }

    public function message()
    {
        return match ($this->reason) {
            \libphonenumber\ValidationResult::IS_POSSIBLE => 'The :attribute is not valid for an unknown reason.',
            \libphonenumber\ValidationResult::INVALID_COUNTRY_CODE => 'The :attribute does not have a valid country code.',
            \libphonenumber\ValidationResult::TOO_SHORT => 'The :attribute is too short.',
            \libphonenumber\ValidationResult::TOO_LONG => 'The :attribute is too long.',
            \libphonenumber\ValidationResult::IS_POSSIBLE_LOCAL_ONLY => 'The :attribute is not a valid phone number (local number).',
            \libphonenumber\ValidationResult::INVALID_LENGTH => 'The :attribute does not have a valid length.',
            default => 'The :attribute is not a valid phone number.',
        };
    }
}
