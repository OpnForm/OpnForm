<?php

namespace App\Http\Requests\Pdf;

use App\Models\Forms\Form;
use App\Rules\PdfZoneMappingsRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePdfTemplateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Form $form */
        $form = $this->route('form');

        return [
            'name' => 'required|string|max:255',
            'zone_mappings' => ['sometimes', 'array', new PdfZoneMappingsRule($form)],
            'filename_pattern' => 'sometimes|string|max:255',
            'remove_branding' => 'sometimes|boolean',
            'page_count' => 'sometimes|integer|min:1',
            'new_pages' => 'sometimes|array',
            'new_pages.*' => 'integer|min:1',
            'removed_pages' => 'sometimes|array',
            'removed_pages.*' => 'integer|min:1',
        ];
    }
}
