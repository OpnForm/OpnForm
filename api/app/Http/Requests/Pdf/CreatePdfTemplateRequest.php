<?php

namespace App\Http\Requests\Pdf;

use Illuminate\Foundation\Http\FormRequest;

class CreatePdfTemplateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => 'nullable|file|mimes:pdf|max:5120',
            'name' => 'nullable|string|max:255',
        ];
    }
}
