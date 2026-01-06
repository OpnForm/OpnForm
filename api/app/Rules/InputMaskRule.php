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
            // Escape the mask pattern to prevent XSS in error messages
            $safeMask = htmlspecialchars($this->mask, ENT_QUOTES, 'UTF-8');
            $fail("Does not match the required format: " . $safeMask);
        }
    }

    private function validateMaskPattern(string $mask): bool
    {
        return (bool) preg_match('/^[9a*().\s\-?]*$/', $mask);
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
                    // For optional tokens, check if we should consume or skip
                    // If skipping would allow us to match all remaining required tokens and literals, skip it
                    if ($maskToken['optional']) {
                        // Calculate remaining required pattern tokens and literals after this optional one
                        $remainingRequired = 0;
                        $remainingLiterals = 0;
                        for ($j = $maskIndex + 1; $j < count($maskTokens); $j++) {
                            if ($maskTokens[$j]['literal']) {
                                $remainingLiterals++;
                            } elseif (!$maskTokens[$j]['optional']) {
                                $remainingRequired++;
                            }
                        }

                        // Calculate remaining input characters (excluding already matched literals)
                        $remainingInput = strlen($value) - $valueIndex;

                        // If we don't have enough input for all remaining required tokens + literals, skip this optional
                        if ($remainingInput <= ($remainingRequired + $remainingLiterals)) {
                            $maskIndex++; // Skip the optional mask token
                            continue;
                        }
                    }

                    // Consume the character
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
        $patterns = [
            '9' => '/[0-9]/',
            'a' => '/[a-zA-Z]/',
            '*' => '/[a-zA-Z0-9]/'
        ];

        for ($i = 0; $i < strlen($mask); $i++) {
            $char = $mask[$i];

            // If we encounter '?', mark the previous token as optional
            if ($char === '?') {
                if (count($tokens) > 0) {
                    $tokens[count($tokens) - 1]['optional'] = true;
                }
                continue;
            }

            $tokens[] = [
                'char' => $char,
                'regex' => $patterns[$char] ?? null,
                'literal' => !isset($patterns[$char]),
                'optional' => false
            ];
        }

        return $tokens;
    }
}
