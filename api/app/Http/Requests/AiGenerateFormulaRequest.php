<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiGenerateFormulaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'formula_prompt' => 'required|string|max:10000',
            'context' => 'nullable|array',
            'context.fields' => 'nullable|array',
            'context.computed_variables' => 'nullable|array',
            'context.current_formula' => 'nullable|string|max:10000',
            'context.current_variable' => 'nullable|array',
            'context.current_variable.name' => 'nullable|string|max:255',
        ];
    }
}
