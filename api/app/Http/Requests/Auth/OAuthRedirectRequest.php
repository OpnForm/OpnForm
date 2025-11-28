<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class OAuthRedirectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'intent' => 'required|in:auth,integration',
            'invite_token' => 'sometimes|string',
            'intention' => 'sometimes|string',
            'autoClose' => 'sometimes|boolean',
            'utm_data' => 'sometimes|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'intent.required' => 'The intent field is required.',
            'intent.in' => 'The intent must be either `auth` or `integration`.',
        ];
    }
}
