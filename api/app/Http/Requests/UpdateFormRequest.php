<?php

namespace App\Http\Requests;

class UpdateFormRequest extends UserFormRequest
{
    public function authorize(): bool
    {
        return $this->form !== null
            && $this->user() !== null
            && $this->user()->can('update', $this->form);
    }
}
