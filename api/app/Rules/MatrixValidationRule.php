<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MatrixValidationRule implements ValidationRule
{
    protected $field;
    protected $isRequired;

    public function __construct(array $field, bool $isRequired)
    {
        $this->field = $field;
        $this->isRequired = $isRequired;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->isRequired && empty($value)) {
            return; // If not required and empty, validation passes
        }

        if (!is_array($value)) {
            $fail('The Matrix field must be an array.');
            return;
        }

        $rows = $this->field['rows'];
        $columns = $this->field['columns'];

        foreach ($rows as $row) {
            if (!array_key_exists($row, $value)) {
                if ($this->isRequired) {
                    $fail("Missing value for row '{$row}'.");
                }
                continue;
            }

            $cellValue = $value[$row];

            if ($cellValue === null) {
                if ($this->isRequired) {
                    $fail("Value for row '{$row}' is required.");
                }
                continue;
            }

            if (!in_array($cellValue, $columns)) {
                $fail("Invalid value '{$cellValue}' for row '{$row}'.");
            }
        }

    }
}
