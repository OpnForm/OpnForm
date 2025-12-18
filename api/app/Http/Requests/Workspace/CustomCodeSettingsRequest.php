<?php

namespace App\Http\Requests\Workspace;

use App\Models\Workspace;
use App\Rules\CssOnlyRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class CustomCodeSettingsRequest extends FormRequest
{
    public Workspace $workspace;

    public function __construct(Request $request)
    {
        $this->workspace = $request->route('workspace');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'custom_code' => ['string', 'nullable'],
            'custom_css' => ['string', 'nullable', new CssOnlyRule()],
        ];
    }
}
