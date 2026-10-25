<?php

namespace App\Service\Formulas;

use App\Service\Formulas\Functions\FunctionRegistry;

class Validator
{
    private const FUNCTION_ARGUMENTS = [
        // Math functions
        'SUM' => ['min' => 1],
        'AVERAGE' => ['min' => 1],
        'MIN' => ['min' => 1],
        'MAX' => ['min' => 1],
        'ROUND' => ['min' => 1, 'max' => 2],
        'FLOOR' => ['min' => 1, 'max' => 1],
        'CEIL' => ['min' => 1, 'max' => 1],
        'ABS' => ['min' => 1, 'max' => 1],
        'MOD' => ['min' => 2, 'max' => 2],
        'POWER' => ['min' => 2, 'max' => 2],
        'SQRT' => ['min' => 1, 'max' => 1],

        // Text functions
        'CONCAT' => ['min' => 1],
        'UPPER' => ['min' => 1, 'max' => 1],
        'LOWER' => ['min' => 1, 'max' => 1],
        'TRIM' => ['min' => 1, 'max' => 1],
        'LEFT' => ['min' => 2, 'max' => 2],
        'RIGHT' => ['min' => 2, 'max' => 2],
        'MID' => ['min' => 3, 'max' => 3],
        'LEN' => ['min' => 1, 'max' => 1],
        'SUBSTITUTE' => ['min' => 3, 'max' => 4],
        'REPLACE' => ['min' => 4, 'max' => 4],
        'FIND' => ['min' => 2, 'max' => 3],
        'SEARCH' => ['min' => 2, 'max' => 3],
        'REPT' => ['min' => 2, 'max' => 2],
        'TEXT' => ['min' => 2, 'max' => 2],

        // Logic functions
        'IF' => ['min' => 2, 'max' => 3],
        'AND' => ['min' => 1],
        'OR' => ['min' => 1],
        'NOT' => ['min' => 1, 'max' => 1],
        'XOR' => ['min' => 2],
        'ISBLANK' => ['min' => 1, 'max' => 1],
        'ISNUMBER' => ['min' => 1, 'max' => 1],
        'ISTEXT' => ['min' => 1, 'max' => 1],
        'IFERROR' => ['min' => 2, 'max' => 2],
        'IFBLANK' => ['min' => 2, 'max' => 2],
        'COALESCE' => ['min' => 1],
        'SWITCH' => ['min' => 3],
        'IFS' => ['min' => 2],
        'CHOOSE' => ['min' => 2],

        // Array functions
        'COUNT' => ['min' => 1, 'max' => 1],
        'ISEMPTY' => ['min' => 1, 'max' => 1],
        'CONTAINS' => ['min' => 2, 'max' => 2],
        'JOIN' => ['min' => 1, 'max' => 2],
    ];

    private array $availableFields;
    private array $availableVariables;
    private ?string $currentVariableId;

    public function __construct(array $options = [])
    {
        $this->availableFields = $options['availableFields'] ?? [];
        $this->availableVariables = $options['availableVariables'] ?? [];
        $this->currentVariableId = $options['currentVariableId'] ?? null;
    }

    public function validate(string $formula): ValidationResult
    {
        $result = new ValidationResult();

        if (empty(trim($formula))) {
            $result->addError('Formula cannot be empty');
            return $result;
        }

        try {
            $ast = Parser::parse($formula);
            $this->validateNode($ast, $result);
        } catch (FormulaException $e) {
            $result->addError($e->getMessage(), $e->getPosition());
        } catch (\Throwable $e) {
            $result->addError("Syntax error: {$e->getMessage()}");
        }

        return $result;
    }

    private function validateNode(array $node, ValidationResult $result): void
    {
        switch ($node['type']) {
            case 'field':
                $this->validateFieldReference($node, $result);
                break;
            case 'function':
                $this->validateFunctionCall($node, $result);
                break;
            case 'binary':
                $this->validateNode($node['left'], $result);
                $this->validateNode($node['right'], $result);
                break;
            case 'unary':
                $this->validateNode($node['operand'], $result);
                break;
        }
    }

    private function validateFieldReference(array $node, ValidationResult $result): void
    {
        $fieldId = $node['id'];

        // Check for self-reference
        if ($fieldId === $this->currentVariableId) {
            $result->addError('Variable cannot reference itself');
            return;
        }

        // Check if field exists
        $fieldExists = collect($this->availableFields)->contains('id', $fieldId);
        $variableExists = collect($this->availableVariables)->contains('id', $fieldId);

        if (!$fieldExists && !$variableExists) {
            $suggestion = $this->findSimilarField($fieldId);
            if ($suggestion) {
                $result->addError("Unknown field \"{$fieldId}\". Did you mean \"{$suggestion}\"?");
            } else {
                $result->addError("Unknown field \"{$fieldId}\"");
            }
        }
    }

    private function validateFunctionCall(array $node, ValidationResult $result): void
    {
        $funcName = strtoupper($node['name']);

        if (!FunctionRegistry::has($funcName)) {
            $suggestion = $this->findSimilarFunction($funcName);
            if ($suggestion) {
                $result->addError("Unknown function \"{$funcName}\". Did you mean \"{$suggestion}\"?");
            } else {
                $result->addError("Unknown function \"{$funcName}\"");
            }
            return;
        }

        $requirements = self::FUNCTION_ARGUMENTS[$funcName] ?? null;
        $argumentCount = count($node['args']);

        if ($requirements !== null && isset($requirements['min']) && $argumentCount < $requirements['min']) {
            $minimum = $requirements['min'];
            $argumentLabel = $minimum === 1 ? 'argument' : 'arguments';

            if (($requirements['max'] ?? null) === $minimum) {
                $result->addError("Function {$funcName}() requires exactly {$minimum} {$argumentLabel}, but got {$argumentCount}.");
            } else {
                $result->addError("Function {$funcName}() requires at least {$minimum} {$argumentLabel}, but got {$argumentCount}.");
            }

            return;
        }

        if ($requirements !== null && isset($requirements['max']) && $argumentCount > $requirements['max']) {
            $maximum = $requirements['max'];
            $argumentLabel = $maximum === 1 ? 'argument' : 'arguments';
            $result->addError("Function {$funcName}() accepts at most {$maximum} {$argumentLabel}, but got {$argumentCount}.");

            return;
        }

        // Validate function arguments
        foreach ($node['args'] as $arg) {
            $this->validateNode($arg, $result);
        }
    }

    private function findSimilarField(string $fieldId): ?string
    {
        $allIds = array_merge(
            array_column($this->availableFields, 'id'),
            array_column($this->availableVariables, 'id')
        );

        foreach ($allIds as $id) {
            if (levenshtein(strtolower($fieldId), strtolower($id)) <= 2) {
                return $id;
            }
        }

        return null;
    }

    private function findSimilarFunction(string $funcName): ?string
    {
        $functionNames = FunctionRegistry::getAll();

        foreach ($functionNames as $name) {
            if (levenshtein(strtolower($funcName), strtolower($name)) <= 2) {
                return $name;
            }
        }

        return null;
    }

    public static function extractFieldReferences(string $formula): array
    {
        $references = [];
        preg_match_all('/\{([^}]+)\}/', $formula, $matches);

        foreach ($matches[1] as $match) {
            $references[] = trim($match);
        }

        return $references;
    }
}

class ValidationResult
{
    public bool $valid = true;
    public array $errors = [];
    public array $warnings = [];

    public function addError(string $message, ?int $position = null): void
    {
        $this->valid = false;
        $this->errors[] = [
            'message' => $message,
            'position' => $position,
            'type' => 'error',
        ];
    }

    public function addWarning(string $message, ?int $position = null): void
    {
        $this->warnings[] = [
            'message' => $message,
            'position' => $position,
            'type' => 'warning',
        ];
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
