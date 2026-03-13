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
            'import_data.url' => 'required_unless:source,google_forms|url',
            'import_data.google_access_token' => 'required_if:source,google_forms|string',
            'workspace_id' => 'required|integer|exists:workspaces,id',
        ];
    }

    public function messages(): array
    {
        return [
            'import_data.url.required_unless' => 'A form URL is required for this import source.',
            'import_data.url.url' => 'Please provide a valid URL.',
            'import_data.google_access_token.required_if' => 'Google authentication is required to import Google Forms.',
        ];
    }
}
