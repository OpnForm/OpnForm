<?php

namespace App\Service\Formulas\Functions;

class LogicFunctions
{
    private static function isTruthy(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return $value != 0;
        }
        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === 'false' || $lower === 'no' || $lower === '0') {
                return false;
            }
            return true;
        }
        return (bool) $value;
    }

    private static function isBlankValue(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    public static function IF(mixed $condition, mixed $valueIfTrue, mixed $valueIfFalse = '')
    {
        return self::isTruthy($condition) ? $valueIfTrue : $valueIfFalse;
    }

    public static function AND(...$conditions): bool
    {
        if (empty($conditions)) {
            return true;
        }
        foreach ($conditions as $condition) {
            if (!self::isTruthy($condition)) {
                return false;
            }
        }
        return true;
    }

    public static function OR(...$conditions): bool
    {
        if (empty($conditions)) {
            return false;
        }
        foreach ($conditions as $condition) {
            if (self::isTruthy($condition)) {
                return true;
            }
        }
        return false;
    }

    public static function NOT(mixed $condition): bool
    {
        return !self::isTruthy($condition);
    }

    public static function XOR(...$conditions): bool
    {
        $trueCount = 0;
        foreach ($conditions as $condition) {
            if (self::isTruthy($condition)) {
                $trueCount++;
            }
        }
        return $trueCount % 2 === 1;
    }

    public static function ISBLANK(mixed $value): bool
    {
        return self::isBlankValue($value);
    }

    public static function ISNUMBER(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        return is_numeric($value);
    }

    public static function ISTEXT(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    public static function IFERROR(mixed $value, mixed $fallback = '')
    {
        if ($value === null) {
            return $fallback;
        }
        return $value;
    }

    public static function IFBLANK(mixed $value, mixed $fallback = '')
    {
        if (self::isBlankValue($value)) {
            return $fallback;
        }
        return $value;
    }

    public static function COALESCE(...$values)
    {
        foreach ($values as $value) {
            if (!self::isBlankValue($value)) {
                return $value;
            }
        }
        return null;
    }

    public static function SWITCH(mixed $value, ...$caseResults)
    {
        for ($i = 0; $i < count($caseResults) - 1; $i += 2) {
            if ($value === $caseResults[$i]) {
                return $caseResults[$i + 1];
            }
        }
        // Return default if provided (odd number of arguments)
        if (count($caseResults) % 2 === 1) {
            return $caseResults[count($caseResults) - 1];
        }
        return null;
    }

    public static function IFS(...$conditionResults)
    {
        for ($i = 0; $i < count($conditionResults); $i += 2) {
            if (self::isTruthy($conditionResults[$i])) {
                return $conditionResults[$i + 1] ?? null;
            }
        }
        return null;
    }

    public static function CHOOSE(mixed $index, ...$values)
    {
        $idx = (int) $index;
        if ($idx < 1 || $idx > count($values)) {
            return null;
        }
        return $values[$idx - 1];
    }
}
