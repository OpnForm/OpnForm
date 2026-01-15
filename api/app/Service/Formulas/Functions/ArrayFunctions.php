<?php

namespace App\Service\Formulas\Functions;

class ArrayFunctions
{
    /**
     * COUNT - Returns number of elements in array
     * For non-arrays, returns 1 for non-empty values, 0 for null/undefined
     */
    public static function COUNT(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }
        if (is_array($value)) {
            return count($value);
        }
        if (is_string($value)) {
            return strlen($value) > 0 ? 1 : 0;
        }
        return 1;
    }

    /**
     * ISEMPTY - Returns true if value is empty
     * Handles null, empty strings, and empty arrays
     */
    public static function ISEMPTY(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_array($value)) {
            return count($value) === 0;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        return false;
    }

    /**
     * CONTAINS - Returns true if array contains the search value
     * Case-sensitive comparison
     * For non-arrays, checks equality
     */
    public static function CONTAINS(mixed $array, mixed $searchValue): bool
    {
        if ($array === null) {
            return false;
        }
        if (!is_array($array)) {
            return $array === $searchValue;
        }
        return in_array($searchValue, $array, true);
    }

    /**
     * JOIN - Joins array elements into a string with separator
     * For non-arrays, returns the value as string
     */
    public static function JOIN(mixed $array, string $separator = ', '): string
    {
        if ($array === null) {
            return '';
        }
        if (!is_array($array)) {
            return (string) $array;
        }
        return implode($separator, $array);
    }
}
