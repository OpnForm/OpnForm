<?php

namespace App\Http\Requests\Integration\Zapier;

use App\Models\Forms\Form;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PollSubmissionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
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
        // Use firstOrFail with query optimization instead of findOrFail
        return Form::where('id', $this->input('form_id'))->firstOrFail();
    }
}
