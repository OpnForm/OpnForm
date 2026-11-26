<?php

namespace App\Rules;

use App\Open\MentionParser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class FormRedirectUrlRule implements ValidationRule
{
    public function __construct(private readonly ?string $existingUrl = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->existingUrl !== null && Str::trim($value) === Str::trim($this->existingUrl)) {
            return;
        }

        $url = (new MentionParser($value, []))->urlFriendlyOutput()->parseAsText();

        if (!preg_match('~^https?://~i', $url)) {
            $fail('The redirect URL must start with http:// or https://.');
        }
    }
}
