<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\Version;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DeleteFormSubmission
{
    public function __construct(private FormSummaryService $summaryService)
    {
    }

    public function execute(
        Form $form,
        int $submissionId,
        ?CarbonInterface $updatedBefore = null,
        bool $invalidateCaches = true
    ): bool {
        $deleted = DB::transaction(function () use ($form, $submissionId, $updatedBefore) {
            $query = $form->submissions()->whereKey($submissionId);

            if ($updatedBefore) {
                $query->where('updated_at', '<=', $updatedBefore);
            }

            $submission = $query->lockForUpdate()->first();

            if (!$submission) {
                return false;
            }

            $submission->setRelation('form', $form);

            if (!$submission->delete()) {
                throw new RuntimeException("Unable to delete form submission {$submission->id}.");
            }

            $submission->storedFiles()->delete();

            Version::query()
                ->where('versionable_type', FormSubmission::class)
                ->where('versionable_id', (string) $submission->id)
                ->delete();

            return true;
        });

        if ($deleted && $invalidateCaches) {
            $this->invalidateCachesSafely($form);
        }

        return $deleted;
    }

    public function executeMany(
        Form $form,
        iterable $submissionIds,
        ?CarbonInterface $updatedBefore = null
    ): int {
        $deleted = 0;

        try {
            foreach ($submissionIds as $submissionId) {
                if ($this->execute($form, (int) $submissionId, $updatedBefore, false)) {
                    $deleted++;
                }
            }
        } finally {
            if ($deleted > 0) {
                $this->invalidateCachesSafely($form);
            }
        }

        return $deleted;
    }

    private function invalidateCachesSafely(Form $form): void
    {
        try {
            $form->forget('submissions_count');
            $form->workspace?->forget('submissions_count');
            $this->summaryService->clearFormSummaryCache($form);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
