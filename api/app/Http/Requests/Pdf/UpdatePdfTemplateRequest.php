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
            'name' => 'sometimes|string|max:255',
            'zone_mappings' => ['sometimes', 'array', new PdfZoneMappingsRule($form)],
            'filename_pattern' => 'sometimes|string|max:255',
            'remove_branding' => 'sometimes|boolean',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if user can remove branding (Pro feature)
            if ($this->has('remove_branding') && $this->boolean('remove_branding')) {
                /** @var Form $form */
                $form = $this->route('form');
                if (!$form->workspace->is_pro) {
                    $validator->errors()->add('remove_branding', 'Removing branding requires a Pro subscription.');
                }
            }
        });
    }
}
