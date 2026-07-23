<?php

namespace App\Service\AI\Prompts\Form;

use App\Service\AI\Prompts\Prompt;
use App\Service\Formulas\Functions\FunctionRegistry;
use App\Service\Formulas\Parser;
use App\Service\Formulas\Validator;

class GenerateFormulaPrompt extends Prompt
{
    protected ?float $temperature = 0.3;

    protected ?int $maxTokens = 1024;

    protected string $model = 'gpt-5.4-mini';

    public const PROMPT_TEMPLATE = <<<'EOD'
        Generate a formula for a computed variable based on the user's requirement.

        <requirement>
            {formulaPrompt}
        </requirement>

        <available_fields>
        {availableFields}
        </available_fields>

        <available_computed_variables>
        {availableVariables}
        </available_computed_variables>

        <critical_rules>
        - ONLY reference fields and variables from the lists above — never invent field names
        - Every dynamic value MUST be wrapped in curly braces as a mention: {Field Name}
        - The formula MUST be syntactically valid:
          * Every opening parenthesis "(" must have a matching closing ")"
          * Function calls always end with a closing parenthesis, e.g. IF(cond, a, b)
          * Commas separate function arguments inside the parentheses
          * No trailing commas or missing arguments
        - Double-check parentheses balance before responding
        </critical_rules>

        <formula_syntax>
        - Field mentions: {Field Name} — must match an available field exactly
        - Variable mentions: {Variable Name} — must match an available computed variable exactly
        - Operators: +, -, *, /
        - Comparisons: =, <>, <, >, <=, >=
        - Functions: FUNCTION_NAME(arg1, arg2, ...)
        - Strings: "hello" (double quotes only)
        - Booleans: TRUE, FALSE
        - Numbers: 42, 3.14, 0.9
        </formula_syntax>

        <available_functions>
        {availableFunctions}
        </available_functions>

        <examples>
        - Multiply price by quantity: {Price} * {Quantity}
        - 10% discount for bulk: IF({Quantity} > 100, {Price} * {Quantity} * 0.9, {Price} * {Quantity})
        - Full name: CONCAT({First Name}, " ", {Last Name})
        - Null-safe total: IFBLANK({Price}, 0) * IFBLANK({Quantity}, 1)
        - Nested IF: IF({Score} > 90, "A", IF({Score} > 80, "B", "C"))
        </examples>

        Return ONLY the formula string. No explanations, no markdown, no code blocks.
    EOD;

    protected ?array $jsonSchema = [
        'type' => 'object',
        'required' => ['formula'],
        'additionalProperties' => false,
        'properties' => [
            'formula' => [
                'type' => 'string',
                'description' => 'The generated formula using field/variable names in curly braces',
            ],
        ],
    ];

    public function __construct(
        public string $formulaPrompt,
        public array $fields = [],
        public array $computedVariables = [],
    ) {
        parent::__construct();
    }

    protected function getSystemMessage(): ?string
    {
        return 'You are an AI assistant specialized in generating formulas for OpnForm computed variables. You only output syntactically valid formulas. Every field reference uses curly-brace mentions. Always ensure parentheses are balanced and function calls are complete.';
    }

    protected function getPromptTemplate(): string
    {
        return self::PROMPT_TEMPLATE;
    }

    public function execute(): array
    {
        $maxAttempts = 3;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $result = parent::execute();
            $formula = trim($result['formula'] ?? '');

            // Strip markdown code fences if AI wraps the formula
            $formula = preg_replace('/^```[a-z]*\s*/i', '', $formula);
            $formula = preg_replace('/\s*```$/', '', $formula);
            $formula = trim($formula);

            if ($formula === '') {
                $lastError = 'AI returned an empty formula.';
                continue;
            }

            // Convert field names to IDs for validation
            $storageFormula = $this->convertMentionsToIds($formula);

            // Validate syntax via Parser
            try {
                Parser::parse($storageFormula);
            } catch (\Throwable $e) {
                $lastError = "Syntax error: {$e->getMessage()}";
                continue;
            }

            // Validate field references
            $validator = new Validator([
                'availableFields' => $this->buildValidatorFields(),
                'availableVariables' => $this->buildValidatorVariables(),
            ]);
            $validationResult = $validator->validate($storageFormula);

            if (! $validationResult->valid) {
                $lastError = $validationResult->errors[0]['message'] ?? 'Invalid formula';
                continue;
            }

            return [
                'formula' => $storageFormula,
            ];
        }

        throw new \RuntimeException("Failed to generate a valid formula after {$maxAttempts} attempts. Last error: {$lastError}");
    }

    protected function getPromptVariables(): array
    {
        $variables = parent::getPromptVariables();
        $variables['{availableFields}'] = $this->formatFields();
        $variables['{availableVariables}'] = $this->formatComputedVariables();
        $variables['{availableFunctions}'] = $this->formatFunctions();

        return $variables;
    }

    private function convertMentionsToIds(string $formula): string
    {
        $nameToId = [];

        foreach ($this->fields as $field) {
            if (! empty($field['name']) && ! empty($field['id'])) {
                $nameToId[strtolower($field['name'])] = $field['id'];
            }
        }

        foreach ($this->computedVariables as $variable) {
            if (! empty($variable['name']) && ! empty($variable['id'])) {
                $nameToId[strtolower($variable['name'])] = $variable['id'];
            }
        }

        if (empty($nameToId)) {
            return $formula;
        }

        return preg_replace_callback('/\{([^}]+)\}/', function (array $matches) use ($nameToId) {
            $name = trim($matches[1]);
            $id = $nameToId[strtolower($name)] ?? null;

            return '{' . ($id ?? $name) . '}';
        }, $formula);
    }

    private function buildValidatorFields(): array
    {
        return array_map(fn ($f) => [
            'id' => $f['id'] ?? $f['name'] ?? '',
            'name' => $f['name'] ?? '',
            'type' => $f['type'] ?? 'text',
        ], $this->fields);
    }

    private function buildValidatorVariables(): array
    {
        return array_map(fn ($v) => [
            'id' => $v['id'] ?? $v['name'] ?? '',
            'name' => $v['name'] ?? '',
        ], $this->computedVariables);
    }

    private function formatFields(): string
    {
        if (empty($this->fields)) {
            return 'No form fields available';
        }

        $lines = ['Use these exact mentions in the formula:'];
        foreach ($this->fields as $field) {
            $name = $field['name'] ?? 'Unnamed field';
            $type = $field['type'] ?? 'unknown';
            $lines[] = "- {" . $name . "} (type: {$type})";
        }

        return implode("\n", $lines);
    }

    private function formatComputedVariables(): string
    {
        if (empty($this->computedVariables)) {
            return 'No other computed variables available';
        }

        $lines = ['Use these exact mentions in the formula:'];
        foreach ($this->computedVariables as $variable) {
            $name = $variable['name'] ?? 'Unnamed variable';
            $lines[] = "- {" . $name . "}";
        }

        return implode("\n", $lines);
    }

    private function formatFunctions(): string
    {
        $signatures = [
            'SUM(value1, value2, ...)' => 'Adds numbers together',
            'AVERAGE(value1, value2, ...)' => 'Returns the arithmetic mean',
            'MIN(value1, value2, ...)' => 'Returns the smallest value',
            'MAX(value1, value2, ...)' => 'Returns the largest value',
            'ROUND(number, decimals?)' => 'Rounds to decimal places',
            'FLOOR(number)' => 'Rounds down to integer',
            'CEIL(number)' => 'Rounds up to integer',
            'ABS(number)' => 'Absolute value',
            'MOD(number, divisor)' => 'Remainder after division',
            'POWER(base, exponent)' => 'Raises to a power',
            'SQRT(number)' => 'Square root',
            'CONCAT(text1, text2, ...)' => 'Joins text strings',
            'UPPER(text)' => 'Uppercase text',
            'LOWER(text)' => 'Lowercase text',
            'TRIM(text)' => 'Removes leading/trailing spaces',
            'LEFT(text, count)' => 'Leftmost characters',
            'RIGHT(text, count)' => 'Rightmost characters',
            'MID(text, start, count)' => 'Substring',
            'LEN(text)' => 'Text length',
            'IF(condition, trueValue, falseValue)' => 'Conditional',
            'AND(...)' => 'All conditions true',
            'OR(...)' => 'Any condition true',
            'NOT(value)' => 'Logical negation',
            'ISBLANK(value)' => 'True if blank',
            'IFBLANK(value, default)' => 'Returns default if blank',
            'COALESCE(value1, value2, ...)' => 'First non-blank value',
            'COUNT(value)' => 'Count items in multi-select',
            'CONTAINS(array, value)' => 'True if array contains value',
            'JOIN(array, separator)' => 'Join array into text',
        ];

        $available = array_flip(array_map('strtoupper', FunctionRegistry::getAll()));
        $lines = [];

        foreach ($signatures as $signature => $description) {
            $name = strtoupper(strtok($signature, '('));
            if (isset($available[$name])) {
                $lines[] = "- {$signature}: {$description}";
            }
        }

        return implode("\n", $lines);
    }
}
