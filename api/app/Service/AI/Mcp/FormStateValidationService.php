<?php

namespace App\Service\AI\Mcp;

use Illuminate\Support\Facades\Validator;
use RuntimeException;

class FormStateValidationService
{
    public function assertValidForCreate(array $formState): void
    {
        if (empty($formState)) {
            return;
        }

        $this->assertValidShape($formState);
    }

    public function assertValidForUpdate(array $formState): void
    {
        $this->assertValidShape($formState);
    }

    private function assertValidShape(array $formState): void
    {
        $this->assertNoPersonalData($formState);

        $validator = Validator::make($formState, [
            'title' => ['required', 'string'],
            'properties' => ['required', 'array'],
            're_fillable' => ['required', 'boolean'],
            'use_captcha' => ['required', 'boolean'],
            'redirect_url' => ['nullable', 'string'],
            'submitted_text' => ['required', 'string'],
            'uppercase_labels' => ['required', 'boolean'],
            'submit_button_text' => ['required', 'string'],
            're_fill_button_text' => ['required', 'string'],
            'color' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException('form_state validation failed: ' . $validator->errors()->first());
        }
    }

    private function assertNoPersonalData(array $payload): void
    {
        $emailPattern = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i';

        $walker = function ($value) use (&$walker, $emailPattern): void {
            if (is_array($value)) {
                foreach ($value as $nested) {
                    $walker($nested);
                }
                return;
            }

            if (is_string($value) && preg_match($emailPattern, $value)) {
                throw new RuntimeException('form_state validation failed: do not include personal emails in tool arguments.');
            }
        };

        $walker($payload);
    }
}
