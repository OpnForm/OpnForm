<?php

namespace App\Rules;

use App\Service\Formulas\Validator as FormulaValidator;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;

/**
 * Validation rule for computed variables.
 * Validates structure, formula syntax, field references, and circular dependencies.
 */
class ComputedVariablesRule implements ValidationRule, ValidatorAwareRule, DataAwareRule
{
    private ?Validator $validator = null;

    private array $data = [];

    private const VALID_RESULT_TYPES = ['number', 'text', 'auto'];

    /**
     * Set the current validator.
     */
    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Set the data under validation.
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Null or empty is valid (computed variables are optional)
        if ($value === null || $value === []) {
            return;
        }

        if (! is_array($value)) {
            $fail('Computed variables must be an array.');

            return;
        }

        $allErrors = [];
        $seenNames = [];
        $seenIds = [];

        // Get form properties for field reference validation
        $properties = $this->data['properties'] ?? [];
        $availableFields = $this->buildAvailableFields($properties);

        foreach ($value as $index => $variable) {
            $errors = $this->validateVariable($variable, $index, $availableFields, $value, $seenNames, $seenIds);

            foreach ($errors as $field => $message) {
                $errorKey = "computed_variables.{$index}.{$field}";
                if (! isset($allErrors[$errorKey])) {
                    $allErrors[$errorKey] = [];
                }
                $allErrors[$errorKey][] = $message;
            }

            // Track seen names and IDs for uniqueness checks
            if (isset($variable['name']) && is_string($variable['name'])) {
                $seenNames[strtolower($variable['name'])] = $index;
            }
            if (isset($variable['id']) && is_string($variable['id'])) {
                $seenIds[$variable['id']] = $index;
            }
        }

        // Check for circular dependencies
        $circularErrors = $this->detectCircularDependencies($value);
        foreach ($circularErrors as $error) {
            $allErrors['computed_variables'][] = $error;
        }

        // Add errors to validator's message bag
        if ($this->validator && ! empty($allErrors)) {
            foreach ($allErrors as $errorKey => $messages) {
                foreach ($messages as $message) {
                    $this->validator->errors()->add($errorKey, $message);
                }
            }
            $fail('One or more computed variables have validation errors.');
        }
    }

    /**
     * Validate a single computed variable.
     */
    private function validateVariable(
        mixed $variable,
        int $index,
        array $availableFields,
        array $allVariables,
        array $seenNames,
        array $seenIds
    ): array {
        $errors = [];

        if (! is_array($variable)) {
            return ['_' => "Computed variable at index {$index} must be an array."];
        }

        // Validate ID
        if (! isset($variable['id']) || ! is_string($variable['id'])) {
            $errors['id'] = 'The computed variable ID is required.';
        } elseif (! preg_match('/^cv_/', $variable['id'])) {
            $errors['id'] = 'The computed variable ID must start with "cv_".';
        } elseif (isset($seenIds[$variable['id']])) {
            $errors['id'] = 'Duplicate computed variable ID.';
        }

        // Validate name
        if (! isset($variable['name']) || ! is_string($variable['name'])) {
            $errors['name'] = 'The computed variable name is required.';
        } elseif (strlen($variable['name']) === 0) {
            $errors['name'] = 'The computed variable name cannot be empty.';
        } elseif (strlen($variable['name']) > 100) {
            $errors['name'] = 'The computed variable name must not exceed 100 characters.';
        } elseif (isset($seenNames[strtolower($variable['name'])])) {
            $errors['name'] = 'Duplicate computed variable name. Variable names must be unique.';
        }

        // Validate formula
        if (! isset($variable['formula']) || ! is_string($variable['formula'])) {
            $errors['formula'] = 'The formula is required.';
        } elseif (strlen($variable['formula']) === 0) {
            $errors['formula'] = 'The formula cannot be empty.';
        } elseif (strlen($variable['formula']) > 2000) {
            $errors['formula'] = 'The formula must not exceed 2000 characters.';
        } else {
            // Validate formula syntax and field references
            $formulaErrors = $this->validateFormula(
                $variable['formula'],
                $variable['id'] ?? null,
                $availableFields,
                $allVariables
            );
            if (! empty($formulaErrors)) {
                $errors['formula'] = $formulaErrors[0]; // Return first error
            }
        }

        // Validate result_type (optional)
        if (isset($variable['result_type']) && $variable['result_type'] !== null) {
            if (! in_array($variable['result_type'], self::VALID_RESULT_TYPES, true)) {
                $errors['result_type'] = 'The result type must be one of: '.implode(', ', self::VALID_RESULT_TYPES).'.';
            }
        }

        return $errors;
    }

    /**
     * Validate formula syntax and field references.
     */
    private function validateFormula(
        string $formula,
        ?string $currentVariableId,
        array $availableFields,
        array $allVariables
    ): array {
        // Build available variables (excluding current one to prevent self-reference)
        $availableVariables = collect($allVariables)
            ->filter(fn ($v) => isset($v['id']) && $v['id'] !== $currentVariableId)
            ->map(fn ($v) => ['id' => $v['id'], 'name' => $v['name'] ?? ''])
            ->values()
            ->all();

        $validator = new FormulaValidator([
            'availableFields' => $availableFields,
            'availableVariables' => $availableVariables,
            'currentVariableId' => $currentVariableId,
        ]);

        $result = $validator->validate($formula);

        if (! $result->valid) {
            return array_map(fn ($e) => $e['message'], $result->errors);
        }

        return [];
    }

    /**
     * Build available fields array from form properties.
     */
    private function buildAvailableFields(array $properties): array
    {
        return collect($properties)
            ->filter(fn ($p) => isset($p['id']) && isset($p['type']))
            ->map(fn ($p) => [
                'id' => $p['id'],
                'name' => $p['name'] ?? '',
                'type' => $p['type'],
            ])
            ->values()
            ->all();
    }

    /**
     * Detect circular dependencies between computed variables.
     */
    private function detectCircularDependencies(array $variables): array
    {
        $errors = [];
        $graph = [];
        $variableMap = [];

        // Build dependency graph
        foreach ($variables as $variable) {
            if (! isset($variable['id']) || ! isset($variable['formula'])) {
                continue;
            }

            $variableMap[$variable['id']] = $variable['name'] ?? $variable['id'];
            $dependencies = FormulaValidator::extractFieldReferences($variable['formula']);

            // Only keep dependencies that are other computed variables
            $variableDeps = array_filter($dependencies, function ($dep) use ($variables) {
                return collect($variables)->contains('id', $dep);
            });

            $graph[$variable['id']] = $variableDeps;
        }

        // DFS to detect cycles
        $visited = [];
        $recursionStack = [];

        foreach (array_keys($graph) as $nodeId) {
            $cycle = $this->findCycle($nodeId, $graph, $visited, $recursionStack, []);
            if ($cycle !== null) {
                $cycleNames = array_map(fn ($id) => $variableMap[$id] ?? $id, $cycle);
                $errors[] = 'Circular dependency detected: '.implode(' → ', $cycleNames);

                break; // Report only the first cycle
            }
        }

        return $errors;
    }

    /**
     * DFS helper to find cycles in the dependency graph.
     */
    private function findCycle(
        string $nodeId,
        array $graph,
        array &$visited,
        array &$recursionStack,
        array $path
    ): ?array {
        if (isset($recursionStack[$nodeId])) {
            // Found a cycle - extract it from the path
            $cycleStart = array_search($nodeId, $path);

            return array_merge(array_slice($path, $cycleStart), [$nodeId]);
        }

        if (isset($visited[$nodeId])) {
            return null;
        }

        $visited[$nodeId] = true;
        $recursionStack[$nodeId] = true;
        $path[] = $nodeId;

        foreach ($graph[$nodeId] ?? [] as $depId) {
            if (isset($graph[$depId])) {
                $cycle = $this->findCycle($depId, $graph, $visited, $recursionStack, $path);
                if ($cycle !== null) {
                    return $cycle;
                }
            }
        }

        unset($recursionStack[$nodeId]);

        return null;
    }
}
