<?php

namespace App\Rules;

use App\Service\Security\PublicWebhookUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicWebhookUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The :attribute must be a valid URL.');
            return;
        }

        $message = PublicWebhookUrl::validate($value);
        if ($message !== null) {
            $fail($message);
        }
    }
}
