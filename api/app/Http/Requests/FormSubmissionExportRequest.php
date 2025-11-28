<?php

namespace App\Http\Requests;

use App\Models\Forms\Form;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class FormSubmissionExportRequest extends FormRequest
{
    public Form $form;

    public function __construct(Request $request)
    {
        $this->form = $request->route('form');
    }

    public function rules()
    {
        $validColumns = collect(array_merge(
            $this->form->properties,
            $this->form->removed_properties ?? []
        ))->pluck('id')->toArray();
        $validColumns[] = 'created_at';

        return [
            'columns' => 'required|array',
            'columns.*' => ['boolean', 'required'],
            'columns' => [function ($attribute, $value, $fail) use ($validColumns) {
                $submittedColumns = array_keys($value);
                $invalidColumns = array_diff($submittedColumns, $validColumns);
                if (!empty($invalidColumns)) {
                    $fail('The columns contain invalid values: ' . implode(', ', $invalidColumns));
                }
            }],
        ];
    }
}
