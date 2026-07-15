<?php

namespace App\Http\Requests\Workspace;

use App\Models\Workspace;
use App\Service\Forms\ExternalSubmissionFileLinkPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExternalFileLinkSettingsRequest extends FormRequest
{
    public Workspace $workspace;

    public function __construct(Request $request)
    {
        $this->workspace = $request->route('workspace');
    }

    public function rules(): array
    {
        return [
            'expires_in_hours' => [
                'required',
                'integer',
                Rule::in(ExternalSubmissionFileLinkPolicy::ALLOWED_EXPIRATION_HOURS),
            ],
        ];
    }
}
