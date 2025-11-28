<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiGenerateFieldsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $maxLength = $this->user()?->is_pro ? 10000 : 4000;

        return [
            'fields_prompt' => 'required|string|max:' . $maxLength,
            'current_form_structure' => 'nullable|array',
            'generation_params' => 'nullable|array',
            'generation_params.presentation_style' => 'nullable|in:classic,focused',
        ];
    }
}
