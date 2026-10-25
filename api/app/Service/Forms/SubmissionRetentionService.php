<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SubmissionRetentionService
{
    public function __construct(private DeleteFormSubmission $deleteSubmission)
    {
    }

    public function cutoff(Form $form, ?CarbonInterface $reference = null): ?CarbonImmutable
    {
        $value = $form->submission_retention_value;
        $unit = $form->submission_retention_unit;

        if (!$value || !in_array($unit, Form::SUBMISSION_RETENTION_UNITS, true)) {
            return null;
        }

        $reference = CarbonImmutable::instance($reference ?? now());

        return match ($unit) {
            'day' => $reference->subDays($value),
            'week' => $reference->subWeeks($value),
            'month' => $reference->subMonthsNoOverflow($value),
            'year' => $reference->subYearsNoOverflow($value),
        };
    }

    public function countExpired(Form $form, ?CarbonInterface $reference = null): int
    {
        $cutoff = $this->cutoff($form, $reference);

        if (!$cutoff) {
            return 0;
        }

        return $form->submissions()
            ->where('updated_at', '<=', $cutoff)
            ->count();
    }

    public function purge(Form $form, ?CarbonInterface $reference = null): int
    {
        $cutoff = $this->cutoff($form, $reference);

        if (!$cutoff) {
            return 0;
        }

        $submissionIds = $form->submissions()
            ->where('updated_at', '<=', $cutoff)
            ->select('id')
            ->lazyById(100)
            ->map(fn (FormSubmission $submission) => $submission->id);

        return $this->deleteSubmission->executeMany($form, $submissionIds, $cutoff);
    }
}
