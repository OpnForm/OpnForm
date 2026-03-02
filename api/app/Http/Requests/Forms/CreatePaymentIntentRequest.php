<?php

namespace App\Http\Requests\Forms;

use App\Open\MentionParser;
use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    public function rules(): array
    {
        return [
            'submission_data' => ['sometimes', 'array'],
            'submission_data.*' => ['nullable'],
        ];
    }

    /**
     * Get formatted submission data for mention parsing.
     */
    public function getFormattedSubmissionData(): array
    {
        return collect($this->input('submission_data', []))
            ->map(fn ($value, $key) => ['id' => $key, 'value' => $value])
            ->values()
            ->all();
    }

    /**
     * Parse and resolve the payment amount from a raw value (which may contain mentions).
     */
    public function resolveAmount(mixed $rawAmount): ?float
    {
        $parsedAmount = (new MentionParser($rawAmount, $this->getFormattedSubmissionData()))
            ->parseAsText();

        $normalized = str_replace(',', '', $parsedAmount);
        $matches = [];
        $amount = null;

        if (preg_match('/-?\d+(?:\.\d+)?/', $normalized, $matches)) {
            $amount = (float) $matches[0];
        }

        return ($amount > 0) ? $amount : null;
    }
}
