<?php

namespace App\Service\Formulas\Functions;

class FunctionRegistry
{
    private static array $functions = [
        // Math functions
        'SUM' => [MathFunctions::class, 'SUM'],
        'AVERAGE' => [MathFunctions::class, 'AVERAGE'],
        'MIN' => [MathFunctions::class, 'MIN'],
        'MAX' => [MathFunctions::class, 'MAX'],
        'ROUND' => [MathFunctions::class, 'ROUND'],
        'FLOOR' => [MathFunctions::class, 'FLOOR'],
        'CEIL' => [MathFunctions::class, 'CEIL'],
        'ABS' => [MathFunctions::class, 'ABS'],
        'MOD' => [MathFunctions::class, 'MOD'],
        'POWER' => [MathFunctions::class, 'POWER'],
        'SQRT' => [MathFunctions::class, 'SQRT'],

        // Text functions
        'CONCAT' => [TextFunctions::class, 'CONCAT'],
        'UPPER' => [TextFunctions::class, 'UPPER'],
        'LOWER' => [TextFunctions::class, 'LOWER'],
        'TRIM' => [TextFunctions::class, 'TRIM'],
        'LEFT' => [TextFunctions::class, 'LEFT'],
        'RIGHT' => [TextFunctions::class, 'RIGHT'],
        'MID' => [TextFunctions::class, 'MID'],
        'LEN' => [TextFunctions::class, 'LEN'],
        'SUBSTITUTE' => [TextFunctions::class, 'SUBSTITUTE'],
        'REPLACE' => [TextFunctions::class, 'REPLACE'],
        'FIND' => [TextFunctions::class, 'FIND'],
        'SEARCH' => [TextFunctions::class, 'SEARCH'],
        'REPT' => [TextFunctions::class, 'REPT'],
        'TEXT' => [TextFunctions::class, 'TEXT'],

        // Logic functions
        'IF' => [LogicFunctions::class, 'IF'],
        'AND' => [LogicFunctions::class, 'AND'],
        'OR' => [LogicFunctions::class, 'OR'],
        'NOT' => [LogicFunctions::class, 'NOT'],
        'XOR' => [LogicFunctions::class, 'XOR'],
        'ISBLANK' => [LogicFunctions::class, 'ISBLANK'],
        'ISNUMBER' => [LogicFunctions::class, 'ISNUMBER'],
        'ISTEXT' => [LogicFunctions::class, 'ISTEXT'],
        'IFERROR' => [LogicFunctions::class, 'IFERROR'],
        'IFBLANK' => [LogicFunctions::class, 'IFBLANK'],
        'COALESCE' => [LogicFunctions::class, 'COALESCE'],
        'SWITCH' => [LogicFunctions::class, 'SWITCH'],
        'IFS' => [LogicFunctions::class, 'IFS'],
        'CHOOSE' => [LogicFunctions::class, 'CHOOSE'],

        // Array functions
        'COUNT' => [ArrayFunctions::class, 'COUNT'],
        'ISEMPTY' => [ArrayFunctions::class, 'ISEMPTY'],
        'CONTAINS' => [ArrayFunctions::class, 'CONTAINS'],
        'JOIN' => [ArrayFunctions::class, 'JOIN'],
    ];

    public static function has(string $name): bool
    {
        return isset(self::$functions[strtoupper($name)]);
    }

    public static function get(string $name): ?callable
    {
        $upperName = strtoupper($name);
        if (!isset(self::$functions[$upperName])) {
            return null;
        }
        return self::$functions[$upperName];
    }

    public static function call(string $name, array $args): mixed
    {
        $func = self::get($name);
        if ($func === null) {
            return null;
        }

        try {
            return call_user_func_array($func, $args);
        } catch (\Throwable $e) {
            // Gracefully handle any errors (ArgumentCountError, TypeError, etc.)
            return null;
        }
    }

    public static function getAll(): array
    {
        return array_keys(self::$functions);
    }
}
