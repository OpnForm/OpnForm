<?php

namespace App\Rules;

use App\Models\Forms\Form;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates PDF zone mappings structure.
 * Ensures all zones have valid fields and positioning.
 */
class PdfZoneMappingsRule implements ValidationRule
{
    private array $errors = [];

    public function __construct(private ?Form $form = null)
    {
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->errors = [];

        if (!is_array($value)) {
            $fail('Zone mappings must be an array.');
            return;
        }

        // Get valid field IDs and computed variable IDs from the form
        $validFieldIds = [];
        $validComputedVariableIds = [];
        if ($this->form) {
            if (is_array($this->form->properties)) {
                $validFieldIds = array_column($this->form->properties, 'id');
            }
            if (is_array($this->form->computed_variables)) {
                $validComputedVariableIds = array_column($this->form->computed_variables, 'id');
            }
        }

        foreach ($value as $index => $zone) {
            $this->validateZone($zone, $index, $validFieldIds, $validComputedVariableIds);
        }

        if (!empty($this->errors)) {
            $fail('Zone mappings validation failed: ' . implode('; ', $this->errors));
        }
    }

    /**
     * Validate a single zone.
     */
    private function validateZone(mixed $zone, int $index, array $validFieldIds, array $validComputedVariableIds = []): void
    {
        if (!is_array($zone)) {
            $this->errors[] = "Zone at index {$index} must be an array";
            return;
        }

        // Required fields
        $required = ['id', 'page_id', 'x', 'y', 'width', 'height'];
        foreach ($required as $field) {
            if (!isset($zone[$field])) {
                $this->errors[] = "Zone {$index}: missing required field '{$field}'";
            }
        }

        if (isset($zone['page_id']) && (!is_string($zone['page_id']) || trim($zone['page_id']) === '')) {
            $this->errors[] = "Zone {$index}: page_id must be a non-empty string";
        }

        // Position and size validation (0-100 percent values)
        $positionFields = ['x', 'y', 'width', 'height'];
        foreach ($positionFields as $field) {
            if (isset($zone[$field])) {
                $val = $zone[$field];
                if (!is_numeric($val) || $val < 0 || $val > 100) {
                    $this->errors[] = "Zone {$index}: {$field} must be a number between 0 and 100";
                }
            }
        }

        // Either field_id, static_text, or static_image must be present (key existence defines zone type)
        $fieldId = $zone['field_id'] ?? null;
        $hasFieldId = is_string($fieldId) && trim($fieldId) !== '';
        $hasStaticText = array_key_exists('static_text', $zone);
        $hasStaticImage = array_key_exists('static_image', $zone);

        if (array_key_exists('field_id', $zone) && $fieldId !== null && $fieldId !== '' && !$hasFieldId) {
            $this->errors[] = "Zone {$index}: field_id must be a non-empty string";
        }

        if (!$hasFieldId && !$hasStaticText && !$hasStaticImage) {
            $this->errors[] = "Zone {$index}: must have 'field_id', 'static_text', or 'static_image'";
        }

        // static_text and static_image must not be empty when present
        if ($hasStaticText) {
            $text = trim(strip_tags((string) ($zone['static_text'] ?? '')));
            if ($text === '') {
                $this->errors[] = "Static text should not be empty";
            }
        }
        if ($hasStaticImage && (string) ($zone['static_image'] ?? '') === '') {
            $this->errors[] = "Static image should not be empty";
        }

        // If field_id is set and we have a form context, validate it exists
        if ($hasFieldId && $this->form !== null) {
            $specialFields = ['submission_id', 'submission_date', 'form_name'];
            $isValidField = in_array($fieldId, $validFieldIds, true);
            $isSpecialField = in_array($fieldId, $specialFields, true);
            $isComputedVariable = in_array($fieldId, $validComputedVariableIds, true);

            if (!$isValidField && !$isSpecialField && !$isComputedVariable) {
                $this->errors[] = "Zone {$index}: field_id '{$fieldId}' does not exist in form";
            }
        }

        // Font size validation
        if (isset($zone['font_size'])) {
            $fontSize = $zone['font_size'];
            if (!is_int($fontSize) || $fontSize < 6 || $fontSize > 72) {
                $this->errors[] = "Zone {$index}: font_size must be an integer between 6 and 72";
            }
        }

        // Font color validation (hex format)
        if (isset($zone['font_color'])) {
            $fontColor = $zone['font_color'];
            if (!is_string($fontColor) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $fontColor)) {
                $this->errors[] = "Zone {$index}: font_color must be a valid hex color (e.g., #000000)";
            }
        }
    }
}
