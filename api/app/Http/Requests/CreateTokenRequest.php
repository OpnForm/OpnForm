<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTokenRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => [
                'required',
                'string',
            ],
            'abilities.*' => ['string', \Illuminate\Validation\Rule::in(array_merge(\App\Enums\AccessTokenAbility::values(), \App\Enums\AccessTokenAbility::adminValues()))],
            'abilities' => [
                'nullable',
                'array'
            ]
        ];
    }
}
