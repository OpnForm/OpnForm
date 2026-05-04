<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FormImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'source' => 'required|string|in:typeform,tally,fillout,google_forms',
            'import_data' => 'required|array',
            'import_data.url' => 'required|url',
            'import_data.oauth_provider_id' => 'nullable|integer|exists:oauth_providers,id|required_if:source,google_forms',
            'workspace_id' => 'required|integer|exists:workspaces,id',
        ];
    }

    public function messages(): array
    {
        return [
            'import_data.url.required' => 'A form URL is required.',
            'import_data.url.url' => 'Please provide a valid URL.',
            'import_data.oauth_provider_id.required_if' => 'Please select an account to import from.',
        ];
    }
}
