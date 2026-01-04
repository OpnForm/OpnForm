<?php

namespace App\Service\Formulas;

use App\Models\Forms\Form;

class ComputedVariableEvaluator
{
    private array $computedVariables;
    private array $fieldValues;
    private array $evaluatedValues = [];

    public function __construct(array $computedVariables, array $fieldValues)
    {
        $this->computedVariables = $computedVariables;
        $this->fieldValues = $fieldValues;
    }

    /**
     * Evaluate all computed variables for a form submission
     */
    public static function evaluateForSubmission(Form $form, array $submissionData): array
    {
        $computedVariables = $form->computed_variables ?? [];

        if (empty($computedVariables)) {
            return [];
        }

        $evaluator = new self($computedVariables, $submissionData);
        return $evaluator->evaluateAll();
    }

    /**
     * Evaluate all computed variables in dependency order
     */
    public function evaluateAll(): array
    {
        if (empty($this->computedVariables)) {
            return [];
        }

        // Build dependency graph and get evaluation order
        $resolver = DependencyResolver::fromVariables($this->computedVariables);

        try {
            $order = $resolver->getEvaluationOrder();
        } catch (FormulaException $e) {
            // If there's a cycle, evaluate what we can
            $order = array_column($this->computedVariables, 'id');
        }

        // Evaluate in order
        foreach ($order as $variableId) {
            $this->evaluateVariable($variableId);
        }

        return $this->evaluatedValues;
    }

    /**
     * Evaluate a single computed variable
     */
    private function evaluateVariable(string $variableId): mixed
    {
        // Return cached value if already evaluated
        if (isset($this->evaluatedValues[$variableId])) {
            return $this->evaluatedValues[$variableId];
        }

        // Find the variable
        $variable = collect($this->computedVariables)->firstWhere('id', $variableId);
        if (!$variable) {
            return null;
        }

        // Build context with field values and already-evaluated computed values
        $context = array_merge($this->fieldValues, $this->evaluatedValues);

        // Evaluate the formula
        $result = Evaluator::evaluate($variable['formula'] ?? '', $context);

        // Cache and return
        $this->evaluatedValues[$variableId] = $result;
        return $result;
    }

    /**
     * Get a specific computed variable value
     */
    public function getValue(string $variableId): mixed
    {
        if (!isset($this->evaluatedValues[$variableId])) {
            $this->evaluateVariable($variableId);
        }
        return $this->evaluatedValues[$variableId] ?? null;
    }

    /**
     * Get all evaluated values
     */
    public function getValues(): array
    {
        return $this->evaluatedValues;
    }

    /**
     * Get computed variable by ID with its evaluated value
     */
    public function getVariableWithValue(string $variableId): ?array
    {
        $variable = collect($this->computedVariables)->firstWhere('id', $variableId);
        if (!$variable) {
            return null;
        }

        return array_merge($variable, [
            'value' => $this->getValue($variableId),
        ]);
    }
}
