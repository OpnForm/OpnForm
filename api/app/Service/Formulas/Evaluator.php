<?php

namespace App\Service\Formulas;

use App\Service\Formulas\Functions\FunctionRegistry;

class Evaluator
{
    private const MAX_DEPTH = 10;

    private array $context;

    private int $depth = 0;

    public function __construct(array $context = [])
    {
        $this->context = $context;
    }

    public static function evaluate(string $formula, array $context = []): mixed
    {
        try {
            $ast = Parser::parse($formula);
            $evaluator = new self($context);
            return $evaluator->evaluateNode($ast);
        } catch (FormulaException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function evaluateNode(array $node): mixed
    {
        return match ($node['type']) {
            'number' => $node['value'],
            'string' => $node['value'],
            'boolean' => $node['value'],
            'field' => $this->evaluateField($node),
            'binary' => $this->evaluateBinary($node),
            'unary' => $this->evaluateUnary($node),
            'function' => $this->evaluateFunction($node),
            default => throw new FormulaException("Unknown node type: {$node['type']}"),
        };
    }

    private function evaluateField(array $node): mixed
    {
        $fieldId = $node['id'];
        return $this->context[$fieldId] ?? null;
    }

    private function evaluateBinary(array $node): mixed
    {
        $left = $this->evaluateNode($node['left']);
        $right = $this->evaluateNode($node['right']);
        $operator = $node['operator'];

        // Handle comparison operators
        return match ($operator) {
            '=' => $this->compareEqual($left, $right),
            '<>' => !$this->compareEqual($left, $right),
            '<' => $this->compareLessThan($left, $right),
            '>' => $this->compareGreaterThan($left, $right),
            '<=' => $this->compareLessThanOrEqual($left, $right),
            '>=' => $this->compareGreaterThanOrEqual($left, $right),
            '+' => $this->add($left, $right),
            '-' => $this->subtract($left, $right),
            '*' => $this->multiply($left, $right),
            '/' => $this->divide($left, $right),
            default => throw new FormulaException("Unknown operator: {$operator}"),
        };
    }

    private function add(mixed $left, mixed $right): mixed
    {
        // String concatenation with +
        if (is_string($left) || is_string($right)) {
            return ($left ?? '') . ($right ?? '');
        }

        $leftNum = $this->toNumber($left);
        $rightNum = $this->toNumber($right);

        if ($leftNum === null || $rightNum === null) {
            return null;
        }

        return $leftNum + $rightNum;
    }

    private function subtract(mixed $left, mixed $right): ?float
    {
        $leftNum = $this->toNumber($left);
        $rightNum = $this->toNumber($right);

        if ($leftNum === null || $rightNum === null) {
            return null;
        }

        return $leftNum - $rightNum;
    }

    private function multiply(mixed $left, mixed $right): ?float
    {
        $leftNum = $this->toNumber($left);
        $rightNum = $this->toNumber($right);

        if ($leftNum === null || $rightNum === null) {
            return null;
        }

        return $leftNum * $rightNum;
    }

    private function divide(mixed $left, mixed $right): ?float
    {
        $leftNum = $this->toNumber($left);
        $rightNum = $this->toNumber($right);

        if ($leftNum === null || $rightNum === null || $rightNum == 0) {
            return null;
        }

        return $leftNum / $rightNum;
    }

    private function evaluateUnary(array $node): mixed
    {
        $operand = $this->evaluateNode($node['operand']);
        $operator = $node['operator'];

        return match ($operator) {
            '-' => $this->toNumber($operand) !== null ? -$this->toNumber($operand) : null,
            'NOT' => !$this->toBoolean($operand),
            default => throw new FormulaException("Unknown unary operator: {$operator}"),
        };
    }

    private function evaluateFunction(array $node): mixed
    {
        $this->depth++;

        if ($this->depth > self::MAX_DEPTH) {
            $this->depth--;
            throw new FormulaException('Maximum formula nesting depth exceeded');
        }

        try {
            $funcName = strtoupper($node['name']);

            if (!FunctionRegistry::has($funcName)) {
                throw new FormulaException("Unknown function: {$funcName}");
            }

            // Evaluate all arguments
            $args = array_map(fn ($arg) => $this->evaluateNode($arg), $node['args']);

            return FunctionRegistry::call($funcName, $args);
        } catch (FormulaException $e) {
            throw $e;
        } catch (\Exception $e) {
            return null;
        } finally {
            $this->depth--;
        }
    }

    private function toNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }
        return null;
    }

    private function toBoolean(mixed $value): bool
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

    private function compareEqual(mixed $left, mixed $right): bool
    {
        if ($left === null && $right === null) {
            return true;
        }
        if ($left === null || $right === null) {
            return false;
        }

        // Try numeric comparison first
        $leftNum = $this->toNumber($left);
        $rightNum = $this->toNumber($right);
        if ($leftNum !== null && $rightNum !== null) {
            return $leftNum === $rightNum;
        }

        // String comparison (case-insensitive)
        if (is_string($left) || is_string($right)) {
            return strtolower((string) $left) === strtolower((string) $right);
        }

        return $left === $right;
    }

    private function compareLessThan(mixed $left, mixed $right): bool
    {
        $leftNum = $this->toNumber($left);
        $rightNum = $this->toNumber($right);
        if ($leftNum === null || $rightNum === null) {
            return false;
        }
        return $leftNum < $rightNum;
    }

    private function compareGreaterThan(mixed $left, mixed $right): bool
    {
        $leftNum = $this->toNumber($left);
        $rightNum = $this->toNumber($right);
        if ($leftNum === null || $rightNum === null) {
            return false;
        }
        return $leftNum > $rightNum;
    }

    private function compareLessThanOrEqual(mixed $left, mixed $right): bool
    {
        $leftNum = $this->toNumber($left);
        $rightNum = $this->toNumber($right);
        if ($leftNum === null || $rightNum === null) {
            return false;
        }
        return $leftNum <= $rightNum;
    }

    private function compareGreaterThanOrEqual(mixed $left, mixed $right): bool
    {
        $leftNum = $this->toNumber($left);
        $rightNum = $this->toNumber($right);
        if ($leftNum === null || $rightNum === null) {
            return false;
        }
        return $leftNum >= $rightNum;
    }
}
