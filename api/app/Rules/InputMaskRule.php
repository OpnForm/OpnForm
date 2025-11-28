<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class InputMaskRule implements ValidationRule
{
    private string $mask;

    public function __construct(string $mask)
    {
        $this->mask = $mask;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->validateMaskPattern($this->mask)) {
            $fail("Invalid mask pattern.");
            return;
        }

        if ($value && !$this->validateValueAgainstMask($value, $this->mask)) {
            $fail("Does not match the required format: " . $this->mask);
        }
    }

    private function validateMaskPattern(string $mask): bool
    {
        return preg_match('/^[9a*().\\-?\\s]*$/', $mask);
    }

    private function validateValueAgainstMask(string $value, string $mask): bool
    {
        $maskTokens = $this->parseMask($mask);
        $valueIndex = 0;
        $maskIndex = 0;

        while ($maskIndex < count($maskTokens)) {
            $maskToken = $maskTokens[$maskIndex];

            if ($valueIndex >= strlen($value)) {
                // If we run out of value characters, check if the rest of the mask is optional
                for ($i = $maskIndex; $i < count($maskTokens); $i++) {
                    if (!$maskTokens[$i]['optional']) {
                        return false;
                    }
                }
                return true;
            }

            $char = $value[$valueIndex];

            if ($maskToken['literal']) {
                // Literal characters must match exactly
                if ($char !== $maskToken['char']) {
                    return false;
                }
                $valueIndex++;
                $maskIndex++;
            } else {
                // Pattern characters must match the regex
                if (preg_match($maskToken['regex'], $char)) {
                    $valueIndex++;
                    $maskIndex++;
                } else {
                    // If pattern doesn't match, check if it was optional
                    if ($maskToken['optional']) {
                        $maskIndex++; // Skip the optional mask token
                    } else {
                        return false;
                    }
                }
            }
        }

        // After processing the mask, there should be no extra characters in the value
        return $valueIndex === strlen($value);
    }

    private function parseMask(string $mask): array
    {
        $tokens = [];
        $optional = false;
        $patterns = [
            '9' => '/[0-9]/',
            'a' => '/[a-zA-Z]/',
            '*' => '/[a-zA-Z0-9]/'
        ];

        for ($i = 0; $i < strlen($mask); $i++) {
            $char = $mask[$i];

            if ($char === '?') {
                $optional = true;
                continue;
            }

            $tokens[] = [
                'char' => $char,
                'regex' => $patterns[$char] ?? null,
                'literal' => !isset($patterns[$char]),
                'optional' => $optional
            ];
        }

        return $tokens;
    }
}
