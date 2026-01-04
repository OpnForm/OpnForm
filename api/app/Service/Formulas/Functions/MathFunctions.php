<?php

namespace App\Service\Formulas\Functions;

class MathFunctions
{
    private static function toNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        return null;
    }

    private static function getNumbers(array $values): array
    {
        $numbers = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                $numbers = array_merge($numbers, self::getNumbers($value));
            } else {
                $num = self::toNumber($value);
                if ($num !== null) {
                    $numbers[] = $num;
                }
            }
        }
        return $numbers;
    }

    public static function SUM(...$values): float
    {
        $numbers = self::getNumbers($values);
        return array_sum($numbers);
    }

    public static function AVERAGE(...$values): ?float
    {
        $numbers = self::getNumbers($values);
        if (empty($numbers)) {
            return null;
        }
        return array_sum($numbers) / count($numbers);
    }

    public static function MIN(...$values): ?float
    {
        $numbers = self::getNumbers($values);
        if (empty($numbers)) {
            return null;
        }
        return min($numbers);
    }

    public static function MAX(...$values): ?float
    {
        $numbers = self::getNumbers($values);
        if (empty($numbers)) {
            return null;
        }
        return max($numbers);
    }

    public static function ROUND(mixed $value, int $decimals = 0): ?float
    {
        $num = self::toNumber($value);
        if ($num === null) {
            return null;
        }
        return round($num, $decimals);
    }

    public static function FLOOR(mixed $value): ?float
    {
        $num = self::toNumber($value);
        if ($num === null) {
            return null;
        }
        return floor($num);
    }

    public static function CEIL(mixed $value): ?float
    {
        $num = self::toNumber($value);
        if ($num === null) {
            return null;
        }
        return ceil($num);
    }

    public static function ABS(mixed $value): ?float
    {
        $num = self::toNumber($value);
        if ($num === null) {
            return null;
        }
        return abs($num);
    }

    public static function MOD(mixed $value, mixed $divisor): ?float
    {
        $num = self::toNumber($value);
        $div = self::toNumber($divisor);
        if ($num === null || $div === null || $div === 0) {
            return null;
        }
        return fmod($num, $div);
    }

    public static function POWER(mixed $base, mixed $exponent): ?float
    {
        $b = self::toNumber($base);
        $e = self::toNumber($exponent);
        if ($b === null || $e === null) {
            return null;
        }
        return pow($b, $e);
    }

    public static function SQRT(mixed $value): ?float
    {
        $num = self::toNumber($value);
        if ($num === null || $num < 0) {
            return null;
        }
        return sqrt($num);
    }
}
