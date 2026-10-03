<?php

namespace App\Rules;

use App\Models\Forms\Form;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CustomSlugRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(protected ?Form $form = null)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== null && !is_string($value)) {
            $fail('The custom slug must be a string.');
            return;
        }
        if ($value === null || empty(trim($value)) || !config('app.self_hosted')) {
            return;
        }

        if ($this->form && $this->form->slug === $value) {
            return;
        }

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            $fail('The custom slug can only contain lowercase letters, numbers, and hyphens.');
            return;
        }

        // Check if the slug is unique, excluding current form
        $query = Form::where('slug', $value);
        if ($this->form) {
            $query->where('id', '!=', $this->form->id);
        }

        if ($query->exists()) {
            $fail('This slug is already in use. Please choose another one.');
            return;
        }
    }
}
