<?php

namespace App\Rules;

use App\Service\Formulas\DependencyResolver;
use App\Service\Formulas\Validator as FormulaValidator;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Validation rule for computed variables.
 * Validates structure, formula syntax, field references, and circular dependencies.
 */
class ComputedVariablesRule implements ValidationRule, ValidatorAwareRule, DataAwareRule
{
    public const MAX_VARIABLE_COUNT = 500;

    private const MAX_CHAIN_DEPTH = 20;

    private const VALID_RESULT_TYPES = ['number', 'text', 'auto'];

    private ?Validator $validator = null;

    private array $data = [];

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

        if (count($value) > self::MAX_VARIABLE_COUNT) {
            $fail('A form cannot contain more than '.self::MAX_VARIABLE_COUNT.' computed variables.');

            return;
        }

        $allErrors = [];
        $seenNames = [];
        $seenIds = [];

        // Get form properties for field reference validation
        $properties = is_array($this->data['properties'] ?? null)
            ? $this->data['properties']
            : [];
        $availableFields = $this->buildAvailableFields($properties);
        $fieldIds = array_fill_keys(array_column($availableFields, 'id'), true);

        foreach ($value as $index => $variable) {
            $errors = $this->validateVariable($variable, $index, $availableFields, $value, $seenNames, $seenIds, $fieldIds);

            foreach ($errors as $field => $messages) {
                $errorKey = "computed_variables.{$index}.{$field}";
                if (! isset($allErrors[$errorKey])) {
                    $allErrors[$errorKey] = [];
                }
                foreach ((array) $messages as $message) {
                    $allErrors[$errorKey][] = $message;
                }
            }

            // Track seen names and IDs for uniqueness checks
            if (isset($variable['name']) && is_string($variable['name'])) {
                $seenNames[Str::lower($variable['name'])] = $index;
            }
            if (isset($variable['id']) && is_string($variable['id'])) {
                $seenIds[$variable['id']] = $index;
            }
        }

        // Check for circular dependencies
        $circularErrors = $this->detectCircularDependencies($value);
        foreach ($circularErrors as $errorKey => $messages) {
            foreach ($messages as $message) {
                $allErrors[$errorKey][] = $message;
            }
        }

        // Check for maximum chain depth (only if no cycles)
        if (empty($circularErrors)) {
            foreach ($this->chainDepthErrors($value) as $errorKey => $messages) {
                foreach ($messages as $message) {
                    $allErrors[$errorKey][] = $message;
                }
            }
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
        array $seenIds,
        array $fieldIds,
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
        } elseif (isset($fieldIds[$variable['id']])) {
            $errors['id'] = "The computed variable ID [{$variable['id']}] is already used by a form field.";
        } elseif (isset($seenIds[$variable['id']])) {
            $errors['id'] = 'Duplicate computed variable ID.';
        }

        // Validate name
        if (! isset($variable['name']) || ! is_string($variable['name'])) {
            $errors['name'] = 'The computed variable name is required.';
        } elseif (trim($variable['name']) === '') {
            $errors['name'] = 'The computed variable name cannot be empty.';
        } elseif (Str::length($variable['name']) > 100) {
            $errors['name'] = 'The computed variable name must not exceed 100 characters.';
        } elseif (isset($seenNames[Str::lower($variable['name'])])) {
            $errors['name'] = 'Duplicate computed variable name. Variable names must be unique.';
        }

        // Validate formula
        if (! isset($variable['formula']) || ! is_string($variable['formula'])) {
            $errors['formula'] = 'The formula is required.';
        } elseif (trim($variable['formula']) === '') {
            $errors['formula'] = 'The formula cannot be empty.';
        } elseif (Str::length($variable['formula']) > 2000) {
            $errors['formula'] = 'The formula must not exceed 2000 characters.';
        } else {
            // Validate formula syntax and field references
            $formulaErrors = $this->validateFormula(
                $variable['formula'],
                is_string($variable['id'] ?? null) ? $variable['id'] : null,
                $availableFields,
                $allVariables
            );
            if (! empty($formulaErrors)) {
                $errors['formula'] = $formulaErrors;
            }
        }

        // Validate result_type (optional)
        if (isset($variable['result_type']) && $variable['result_type'] !== null) {
            if (! in_array($variable['result_type'], self::VALID_RESULT_TYPES, true)) {
                $errors['result_type'] = 'The result type must be one of: ' . implode(', ', self::VALID_RESULT_TYPES) . '.';
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
            ->filter(fn ($v) => is_array($v)
                && is_string($v['id'] ?? null)
                && $v['id'] !== $currentVariableId)
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
            ->filter(fn ($p) => is_array($p)
                && is_string($p['id'] ?? null)
                && is_string($p['type'] ?? null))
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
        $validVariables = collect($variables)
            ->filter(fn ($variable) => is_array($variable)
                && is_string($variable['id'] ?? null)
                && is_string($variable['formula'] ?? null))
            ->values()
            ->all();

        if ($validVariables === []) {
            return [];
        }

        $resolver = DependencyResolver::fromVariables($validVariables);
        $cycles = $resolver->detectCycles();
        $variablesById = collect($variables)
            ->filter(fn ($variable) => is_array($variable) && is_string($variable['id'] ?? null))
            ->keyBy('id');

        foreach ($cycles as $cycle) {
            $cycleIds = array_values(array_unique($cycle));
            $cycleNames = array_map(
                fn (string $id) => $variablesById->get($id)['name'] ?? $id,
                $cycleIds,
            );
            $message = 'Circular dependency detected: '.implode(' → ', [...$cycleNames, $cycleNames[0]]);

            foreach ($cycleIds as $cycleId) {
                $variableIndex = collect($variables)->search(
                    fn ($variable) => is_array($variable) && ($variable['id'] ?? null) === $cycleId,
                );
                if ($variableIndex !== false) {
                    $errors["computed_variables.{$variableIndex}.formula"][] = $message;
                }
            }
        }

        return $errors;
    }

    /**
     * Check if the dependency chain depth exceeds the maximum.
     */
    private function chainDepthErrors(array $variables): array
    {
        $variablesById = collect($variables)
            ->filter(fn ($variable) => is_array($variable)
                && is_string($variable['id'] ?? null)
                && is_string($variable['formula'] ?? null))
            ->keyBy('id');
        $memo = [];

        $depthFor = function (string $variableId) use (&$depthFor, &$memo, $variablesById): int {
            if (isset($memo[$variableId])) {
                return $memo[$variableId];
            }

            $variable = $variablesById->get($variableId);
            if (! is_array($variable)) {
                return 0;
            }

            $depth = 1;
            foreach (FormulaValidator::extractFieldReferences($variable['formula']) as $dependencyId) {
                if ($variablesById->has($dependencyId)) {
                    $depth = max($depth, 1 + $depthFor($dependencyId));
                }
            }

            return $memo[$variableId] = $depth;
        };

        $errors = [];
        foreach ($variables as $index => $variable) {
            if (! is_array($variable) || ! is_string($variable['id'] ?? null) || ! $variablesById->has($variable['id'])) {
                continue;
            }

            $depth = $depthFor($variable['id']);
            if ($depth > self::MAX_CHAIN_DEPTH) {
                $errors["computed_variables.{$index}.formula"][] = "Variable dependency chain is too deep ({$depth} levels). Maximum allowed is ".self::MAX_CHAIN_DEPTH.'.';
            }
        }

        return $errors;
    }
}
