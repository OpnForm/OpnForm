<?php

namespace App\Service\Formulas\Functions;

class TextFunctions
{
    private static function toString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        return (string) $value;
    }

    public static function CONCAT(...$values): string
    {
        return implode('', array_map([self::class, 'toString'], $values));
    }

    public static function UPPER(mixed $value): string
    {
        return mb_strtoupper(self::toString($value));
    }

    public static function LOWER(mixed $value): string
    {
        return mb_strtolower(self::toString($value));
    }

    public static function TRIM(mixed $value): string
    {
        return trim(self::toString($value));
    }

    public static function LEFT(mixed $value, mixed $count): string
    {
        $str = self::toString($value);
        $n = (int) $count;
        if ($n < 0) {
            return '';
        }
        return mb_substr($str, 0, $n);
    }

    public static function RIGHT(mixed $value, mixed $count): string
    {
        $str = self::toString($value);
        $n = (int) $count;
        if ($n < 0) {
            return '';
        }
        return mb_substr($str, -$n);
    }

    public static function MID(mixed $value, mixed $start, mixed $length): string
    {
        $str = self::toString($value);
        $s = (int) $start;
        $l = (int) $length;
        if ($s < 1 || $l < 0) {
            return '';
        }
        // MID uses 1-based indexing
        return mb_substr($str, $s - 1, $l);
    }

    public static function LEN(mixed $value): int
    {
        return mb_strlen(self::toString($value));
    }

    public static function SUBSTITUTE(mixed $text, mixed $oldText, mixed $newText, ?int $instance = null): string
    {
        $str = self::toString($text);
        $old = self::toString($oldText);
        $new = self::toString($newText);

        if ($old === '') {
            return $str;
        }

        if ($instance === null) {
            // Replace all occurrences
            return str_replace($old, $new, $str);
        }

        // Replace specific instance
        if ($instance < 1) {
            return $str;
        }

        $count = 0;
        $pos = 0;
        while (($pos = mb_strpos($str, $old, $pos)) !== false) {
            $count++;
            if ($count === $instance) {
                return mb_substr($str, 0, $pos) . $new . mb_substr($str, $pos + mb_strlen($old));
            }
            $pos += mb_strlen($old);
        }

        return $str;
    }

    public static function REPLACE(mixed $text, mixed $start, mixed $length, mixed $newText): string
    {
        $str = self::toString($text);
        $s = (int) $start;
        $l = (int) $length;
        $new = self::toString($newText);

        if ($s < 1 || $l < 0) {
            return $str;
        }

        // REPLACE uses 1-based indexing
        return mb_substr($str, 0, $s - 1) . $new . mb_substr($str, $s - 1 + $l);
    }

    public static function FIND(mixed $findText, mixed $withinText, int $startPos = 1): ?int
    {
        $find = self::toString($findText);
        $within = self::toString($withinText);

        if ($startPos < 1) {
            return null;
        }

        // FIND uses 1-based indexing
        $pos = mb_strpos($within, $find, $startPos - 1);
        return $pos === false ? null : $pos + 1;
    }

    public static function SEARCH(mixed $findText, mixed $withinText, int $startPos = 1): ?int
    {
        $find = mb_strtolower(self::toString($findText));
        $within = mb_strtolower(self::toString($withinText));

        if ($startPos < 1) {
            return null;
        }

        $pos = mb_strpos($within, $find, $startPos - 1);
        return $pos === false ? null : $pos + 1;
    }

    private const MAX_REPT = 100;

    public static function REPT(mixed $text, mixed $times): string
    {
        $str = self::toString($text);
        $n = (int) $times;
        if ($n < 0) {
            return '';
        }
        // Limit repetitions to prevent memory abuse
        $n = min($n, self::MAX_REPT);

        return str_repeat($str, $n);
    }

    public static function TEXT(mixed $value, string $format = ''): string
    {
        if ($value === null) {
            return '';
        }

        if (!is_numeric($value)) {
            return self::toString($value);
        }

        $num = (float) $value;
        $fmt = strtolower($format);

        if (str_contains($fmt, '%')) {
            // Percentage format
            $decimals = max(0, substr_count($fmt, '0') - 1);
            return number_format($num * 100, $decimals) . '%';
        }

        if (str_contains($fmt, '.')) {
            // Decimal format
            $parts = explode('.', $fmt);
            $decimals = strlen($parts[1] ?? '');
            return number_format($num, $decimals);
        }

        return self::toString($value);
    }
}
