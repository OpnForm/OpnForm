<?php

namespace App\Service\Forms;

use App\Jobs\Form\DeletePendingSubmissionFile;
use App\Models\Forms\FormSubmission;
use App\Models\Forms\FormSubmissionFileDeletion;
use App\Models\Version;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class StageFormSubmissionFileDeletions
{
    public function __construct(private SubmissionFilePathService $filePathService)
    {
    }

    public function execute(FormSubmission $submission): void
    {
        foreach ($this->filePaths($submission) as $path) {
            $deletion = FormSubmissionFileDeletion::create([
                'path' => $path,
                'next_attempt_at' => now(),
            ]);

            DB::afterCommit(function () use ($deletion) {
                try {
                    $deletion->forceFill([
                        'next_attempt_at' => now()->addMinutes(15),
                    ])->save();
                    DeletePendingSubmissionFile::dispatch($deletion->id);
                } catch (Throwable $exception) {
                    report($exception);

                    try {
                        $deletion->forceFill(['next_attempt_at' => now()])->save();
                    } catch (Throwable $resetException) {
                        report($resetException);
                    }
                }
            });
        }
    }

    private function filePaths(FormSubmission $submission): array
    {
        return collect($submission->storedFiles()->pluck('path'))
            ->concat(
                $this->dataSnapshots($submission)
                    ->flatMap(fn (array $data) => $this->filePathService->fromData(
                        $submission->form,
                        $data
                    ))
            )
            ->unique()
            ->values()
            ->all();
    }

    private function dataSnapshots(FormSubmission $submission): Collection
    {
        return collect([$submission->data])
            ->concat(
                $submission->versions()
                    ->get()
                    ->map(fn (Version $version) => $version->getModel()->data)
            )
            ->filter(fn ($data) => is_array($data))
            ->values();
    }
}
