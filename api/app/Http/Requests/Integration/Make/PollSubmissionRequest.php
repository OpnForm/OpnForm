<?php

namespace App\Http\Requests\Integration\Make;

use App\Models\Forms\Form;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PollSubmissionRequest extends FormRequest
{
    public function rules()
    {
        return [
            'form_id' => [
                'required',
                Rule::exists(Form::getModel()->getTable(), 'id'),
            ],
        ];
    }

    public function form(): Form
    {
        return Form::where('id', $this->input('form_id'))->firstOrFail();
    }
}
