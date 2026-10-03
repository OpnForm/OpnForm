<?php

namespace App\Service\Forms;

use Illuminate\Support\Str;

final class FormRegex
{
    public const MAX_PATTERN_LENGTH = 1000;

    public static function isValid(string $pattern): bool
    {
        return self::compile($pattern) !== null;
    }

    public static function matches(string $pattern, string $value): ?bool
    {
        $compiledPattern = self::compile($pattern);
        if ($compiledPattern === null) {
            return null;
        }

        return preg_match($compiledPattern, $value) === 1;
    }

    private static function compile(string $pattern): ?string
    {
        if (Str::length($pattern) > self::MAX_PATTERN_LENGTH) {
            return null;
        }

        $delimiter = null;
        foreach (['~', '#', '%', '!', '@', ';', '`', '='] as $candidate) {
            if (! str_contains($pattern, $candidate)) {
                $delimiter = $candidate;

                break;
            }
        }
        if ($delimiter === null) {
            return null;
        }

        $compiledPattern = $delimiter.$pattern.$delimiter.'u';

        set_error_handler(static fn (): bool => true);
        try {
            $isValid = preg_match($compiledPattern, '') !== false;
        } finally {
            restore_error_handler();
        }

        return $isValid ? $compiledPattern : null;
    }
}
