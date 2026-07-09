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
        ];
    }
}
