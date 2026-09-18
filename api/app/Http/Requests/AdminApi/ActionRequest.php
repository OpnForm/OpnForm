<?php

namespace App\Http\Requests\AdminApi;

use Illuminate\Foundation\Http\FormRequest;

class ActionRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'action_id' => 'required|uuid',
        ];
        $operation = $this->route()->defaults['operation'];
        if (!in_array($operation, ['create-template'], true)) {
            $rules['user_id'] = 'required|integer';
        }
        return $rules + match ($operation) {
            'block-user', 'unblock-user', 'disable-two-factor-authentication' => ['reason' => 'required|string|max:1000'],
            'extend-trial' => ['trial_ends_at' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/']],
            'cancel-subscription' => ['subscription_id' => 'required|integer', 'cancellation_reason' => 'required|string|max:1000'],
            'refund-payment' => ['invoice_id' => 'required|string|max:255', 'refund_reason' => 'required|string|max:1000', 'expected_amount' => 'required|integer|min:1', 'expected_currency' => 'required|string|regex:/^[a-z]{3}$/'],
            'update-customer' => ['billing_email' => 'required|email|max:255', 'billing_name' => 'required|string|max:255'],
            'restore-form' => ['slug' => 'required|string|max:255'],
            'create-template' => ['template_prompt' => 'required|string|max:4000'],
            default => [],
        };
    }

    public function after(): array
    {
        return [function ($validator) {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $key) {
                $validator->errors()->add($key, 'Unknown parameter.');
            }
        }];
    }
}
