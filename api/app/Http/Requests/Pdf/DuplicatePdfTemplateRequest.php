<?php

namespace App\Http\Requests\Pdf;

use Illuminate\Foundation\Http\FormRequest;

class DuplicatePdfTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'target_form_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'field_mappings' => 'sometimes|array',
            'field_mappings.*' => 'nullable|string',
        ];
    }
}
