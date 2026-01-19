<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FormSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'in:all,completed,partial'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function getStatus(): string
    {
        return $this->input('status', 'completed');
    }

    public function getDateFrom(): ?string
    {
        return $this->input('date_from');
    }

    public function getDateTo(): ?string
    {
        return $this->input('date_to');
    }

    public function getOffset(): int
    {
        return (int) $this->input('offset', 0);
    }
}
