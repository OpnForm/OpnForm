<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use Illuminate\Database\Eloquent\Collection;

class FormListStatsLoader
{
    /** @param Collection<int, Form> $forms */
    public function load(Collection $forms): void
    {
        if ($forms->isEmpty()) {
            return;
        }

        // Count only the returned page. Counting before OFFSET also evaluates
        // all preceding forms, making later pages increasingly expensive.
        $counts = Form::query()
            ->select('id')
            ->whereKey($forms->modelKeys())
            ->withCount(['submissions as submissions_count' => fn ($query) => $query->where('status', FormSubmission::STATUS_COMPLETED)])
            ->withTotalViews()
            ->get()
            ->keyBy('id');

        foreach ($forms as $form) {
            $form->setAttribute('submissions_count', (int) ($counts->get($form->id)?->getAttribute('submissions_count') ?? 0));
            $form->setAttribute('total_views_count', (int) ($counts->get($form->id)?->getAttribute('total_views_count') ?? 0));
        }
    }
}
