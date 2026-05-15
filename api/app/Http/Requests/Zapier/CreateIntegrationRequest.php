<?php

namespace App\Http\Requests\Zapier;

use App\Models\Forms\Form;
use App\Rules\PublicWebhookUrlRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateIntegrationRequest extends FormRequest
{
    public function rules()
    {
        return [
            'form_id' => [
                'required',
                Rule::exists(Form::getModel()->getTable(), 'id'),
            ],
            'hookUrl' => [
                'required',
                'url',
                new PublicWebhookUrlRule(),
            ],
        ];
    }

    public function form(): Form
    {
        // Use firstOrFail with query optimization instead of findOrFail
        return Form::where('id', $this->input('form_id'))->firstOrFail();
    }
}
