<?php

namespace App\Service\Formulas;

use App\Service\Formulas\Functions\FunctionRegistry;

class Validator
{
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
