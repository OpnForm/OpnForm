<?php

namespace App\Http\Requests\Integration\Make;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListFormsRequest extends FormRequest
{
    public function rules()
    {
        return [
            'workspace_id' => [
                'required',
                Rule::exists(Workspace::getModel()->getTable(), 'id'),
            ],
        ];
    }

    public function workspace(): Workspace
    {
        return Workspace::findOrFail($this->input('workspace_id'));
    }
}
