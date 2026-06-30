<?php

namespace App\Mcp\Concerns;

use App\Models\Forms\Form;

trait ResolvesForm
{
    protected function resolveForm(string $formId): Form
    {
        $query = Form::with(['workspace.users' => fn ($q) => $q->withPivot('role')]);

        if (is_numeric($formId)) {
            return $query->findOrFail((int) $formId);
        }

        return $query->where('slug', $formId)->firstOrFail();
    }
}
