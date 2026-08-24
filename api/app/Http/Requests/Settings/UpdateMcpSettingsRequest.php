<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateMcpSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-instance-settings');
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }
}
