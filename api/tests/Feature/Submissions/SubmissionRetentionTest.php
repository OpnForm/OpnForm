<?php

use App\Http\Resources\FormResource;
use App\Jobs\Form\DeletePendingSubmissionFile;
use App\Jobs\Form\PurgeExpiredFormSubmissions as PurgeExpiredFormSubmissionsJob;
use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\Forms\FormSubmissionFile;
use App\Models\Forms\FormSubmissionFileDeletion;
use App\Models\Version;
use App\Service\Forms\DeleteFormSubmission;
use App\Service\Forms\FormSummaryService;
use App\Service\Forms\SubmissionRetentionService;
use App\Service\Storage\FileUploadPathService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

afterEach(function () {
    Carbon::setTestNow();
});

function createRetentionForm($test, array $attributes = [])
{
    $user = $test->actingAsUser();
    $workspace = $test->createUserWorkspace($user);

    return $test->createForm($user, $workspace, array_merge([
        'submission_retention_value' => 3,
        'submission_retention_unit' => 'day',
    ], $attributes));
}

function createRetentionSubmission($form, CarbonImmutable $updatedAt, array $data = [])
{
    $submission = $form->submissions()->create([
        'data' => $data,
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);

    $submission->timestamps = false;
    $submission->updated_at = $updatedAt;
    $submission->saveQuietly();
    $submission->timestamps = true;

    return $submission;
}

it('stores a custom submission retention period through the form API', function () {
    $form = createRetentionForm($this, [
        'submission_retention_value' => null,
        'submission_retention_unit' => null,
    ]);
    $formData = (new FormResource($form))->toArray(request());
    $formData['submission_retention_value'] = 17;
    $formData['submission_retention_unit'] = 'day';

    $this->putJson(route('open.forms.update', $form), $formData)
        ->assertSuccessful()
        ->assertJsonPath('form.submission_retention_value', 17)
        ->assertJsonPath('form.submission_retention_unit', 'day');

    expect($form->fresh())
        ->submission_retention_value->toBe(17)
        ->submission_retention_unit->toBe('day');
});

it('disables retention through the form API by clearing both fields', function () {
    $form = createRetentionForm($this);
    $formData = (new FormResource($form))->toArray(request());
    $formData['submission_retention_value'] = null;
    $formData['submission_retention_unit'] = null;

    $this->putJson(route('open.forms.update', $form), $formData)
        ->assertSuccessful()
        ->assertJsonPath('form.submission_retention_value', null)
        ->assertJsonPath('form.submission_retention_unit', null);

    $form->refresh();
    expect($form)
        ->submission_retention_value->toBeNull()
        ->submission_retention_unit->toBeNull();
});

it('requires a complete and valid retention value and unit pair', function (array $overrides, string $invalidField) {
    $form = createRetentionForm($this, [
        'submission_retention_value' => null,
        'submission_retention_unit' => null,
    ]);
    $formData = array_merge((new FormResource($form))->toArray(request()), $overrides);

    $this->putJson(route('open.forms.update', $form), $formData)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($invalidField);
})->with([
    'unit without value' => [
        ['submission_retention_value' => null, 'submission_retention_unit' => 'day'],
        'submission_retention_value',
    ],
    'value without unit' => [
        ['submission_retention_value' => 3, 'submission_retention_unit' => null],
        'submission_retention_unit',
    ],
    'unsupported unit' => [
        ['submission_retention_value' => 3, 'submission_retention_unit' => 'hour'],
        'submission_retention_unit',
    ],
    'zero value' => [
        ['submission_retention_value' => 0, 'submission_retention_unit' => 'day'],
        'submission_retention_value',
    ],
    'value above maximum' => [
        ['submission_retention_value' => 3651, 'submission_retention_unit' => 'day'],
        'submission_retention_value',
    ],
]);

it('does not expose retention settings on public forms', function () {
    $form = createRetentionForm($this);

    $this->actingAsGuest();

    $resource = (new FormResource($form->fresh()))->toArray(request());

    expect($resource)
        ->not->toHaveKey('submission_retention_value')
        ->not->toHaveKey('submission_retention_unit');
});

it('calculates calendar-aware retention cutoffs for every supported unit', function (
    int $value,
    string $unit,
    string $expected
) {
    $form = createRetentionForm($this, [
        'submission_retention_value' => $value,
        'submission_retention_unit' => $unit,
    ]);
    $reference = CarbonImmutable::parse('2024-03-31 12:00:00');

    $cutoff = app(SubmissionRetentionService::class)->cutoff($form, $reference);

    expect($cutoff->toDateTimeString())->toBe($expected);
})->with([
    'days' => [3, 'day', '2024-03-28 12:00:00'],
    'weeks' => [2, 'week', '2024-03-17 12:00:00'],
    'months without overflow' => [1, 'month', '2024-02-29 12:00:00'],
    'years without overflow' => [1, 'year', '2023-03-31 12:00:00'],
]);

it('purges expired submissions based on their last update and keeps newer submissions', function () {
    $now = CarbonImmutable::parse('2026-07-20 12:00:00');
    Carbon::setTestNow($now);
    $form = createRetentionForm($this);
    $expired = createRetentionSubmission($form, $now->subDays(4));
    $boundary = createRetentionSubmission($form, $now->subDays(3));
    $recent = createRetentionSubmission($form, $now->subDays(2));

    $deleted = app(SubmissionRetentionService::class)->purge($form, $now);

    expect($deleted)->toBe(2);
    $this->assertDatabaseMissing('form_submissions', ['id' => $expired->id]);
    $this->assertDatabaseMissing('form_submissions', ['id' => $boundary->id]);
    $this->assertDatabaseHas('form_submissions', ['id' => $recent->id]);
});

it('revalidates the last update while holding the deletion lock', function () {
    $now = CarbonImmutable::parse('2026-07-20 12:00:00');
    $form = createRetentionForm($this);
    $submission = createRetentionSubmission($form, $now->subDays(4));

    $submission->timestamps = false;
    $submission->updated_at = $now->subMinute();
    $submission->saveQuietly();
    $submission->timestamps = true;

    $deleted = app(DeleteFormSubmission::class)->execute(
        $form,
        $submission->id,
        $now->subDays(3)
    );

    expect($deleted)->toBeFalse();
    $this->assertDatabaseHas('form_submissions', ['id' => $submission->id]);
});

it('never deletes a submission belonging to another form', function () {
    $form = createRetentionForm($this);
    $otherForm = $this->createForm(auth()->user(), $form->workspace);
    $submission = createRetentionSubmission(
        $otherForm,
        CarbonImmutable::parse('2026-07-01 12:00:00')
    );

    $deleted = app(DeleteFormSubmission::class)->execute($form, $submission->id);

    expect($deleted)->toBeFalse();
    $this->assertDatabaseHas('form_submissions', [
        'id' => $submission->id,
        'form_id' => $otherForm->id,
    ]);
});

it('keeps an old partial submission that was updated within the retention period', function () {
    $now = CarbonImmutable::parse('2026-07-20 12:00:00');
    Carbon::setTestNow($now);
    $form = createRetentionForm($this);
    $submission = createRetentionSubmission($form, $now->subDay());

    $submission->timestamps = false;
    $submission->created_at = $now->subDays(10);
    $submission->status = FormSubmission::STATUS_PARTIAL;
    $submission->saveQuietly();
    $submission->timestamps = true;

    expect(app(SubmissionRetentionService::class)->purge($form, $now))->toBe(0);
    $this->assertDatabaseHas('form_submissions', ['id' => $submission->id]);
});

it('does not purge submissions when retention is disabled', function () {
    $now = CarbonImmutable::parse('2026-07-20 12:00:00');
    $form = createRetentionForm($this, [
        'submission_retention_value' => null,
        'submission_retention_unit' => null,
    ]);
    $submission = createRetentionSubmission($form, $now->subYear());

    expect(app(SubmissionRetentionService::class)->purge($form, $now))->toBe(0);
    $this->assertDatabaseHas('form_submissions', ['id' => $submission->id]);
});

it('fails closed when persisted retention settings are invalid', function () {
    $now = CarbonImmutable::parse('2026-07-20 12:00:00');
    $form = createRetentionForm($this);
    $form->forceFill(['submission_retention_unit' => 'hour'])->saveQuietly();
    $submission = createRetentionSubmission($form, $now->subYear());

    expect(app(SubmissionRetentionService::class)->purge($form, $now))->toBe(0);
    $this->assertDatabaseHas('form_submissions', ['id' => $submission->id]);
});

it('permanently deletes uploaded files and submission versions', function () {
    Queue::fake();

    Storage::fake();
    $now = CarbonImmutable::parse('2026-07-20 12:00:00');
    Carbon::setTestNow($now);
    $form = createRetentionForm($this);
    $fileFieldId = collect($form->properties)->firstWhere('type', 'files')['id'];
    $fileName = 'expired-document.pdf';
    $filePath = FileUploadPathService::getFileUploadPath($form->id, $fileName);
    Storage::put($filePath, 'expired contents');

    $submission = createRetentionSubmission($form, $now->subDays(4), [
        $fileFieldId => [$fileName],
    ]);
    $submission->update(['data' => [$fileFieldId => [$fileName], 'edited' => true]]);
    $submission->timestamps = false;
    $submission->updated_at = $now->subDays(4);
    $submission->saveQuietly();
    $submission->timestamps = true;

    expect($submission->versions()->count())->toBeGreaterThan(0);

    app(SubmissionRetentionService::class)->purge($form, $now);

    $deletion = FormSubmissionFileDeletion::where('path', $filePath)->firstOrFail();
    (new DeletePendingSubmissionFile($deletion->id))->handle();

    Storage::assertMissing($filePath);
    $this->assertDatabaseMissing('form_submission_file_deletions', ['id' => $deletion->id]);
    $this->assertDatabaseMissing('form_submissions', ['id' => $submission->id]);
    expect(Version::query()
        ->where('versionable_type', FormSubmission::class)
        ->where('versionable_id', (string) $submission->id)
        ->count())->toBe(0);
});

it('deletes replaced files after their submission versions have rotated out', function () {
    Queue::fake();
    Storage::fake();

    $now = CarbonImmutable::parse('2026-07-20 12:00:00');
    $form = createRetentionForm($this);
    $fileFieldId = collect($form->properties)->firstWhere('type', 'files')['id'];
    $oldFileName = 'previous-document.pdf';
    $currentFileName = 'current-document.pdf';
    $oldFilePath = FileUploadPathService::getFileUploadPath($form->id, $oldFileName);
    $currentFilePath = FileUploadPathService::getFileUploadPath($form->id, $currentFileName);
    Storage::put($oldFilePath, 'previous contents');
    Storage::put($currentFilePath, 'current contents');

    $submission = createRetentionSubmission($form, $now->subDays(4), [
        $fileFieldId => [$oldFileName],
    ]);
    $submission->update(['data' => [$fileFieldId => [$currentFileName]]]);

    foreach (range(1, 6) as $revision) {
        $submission->update(['data' => [
            $fileFieldId => [$currentFileName],
            'revision' => $revision,
        ]]);
    }

    expect($submission->versions()->get()->contains(function (Version $version) use ($fileFieldId, $oldFileName) {
        return $version->getModel()->data[$fileFieldId] === [$oldFileName];
    }))->toBeFalse();
    expect(FormSubmissionFile::query()
        ->where('form_submission_id', $submission->id)
        ->whereIn('path', [$oldFilePath, $currentFilePath])
        ->count())->toBe(2);

    app(DeleteFormSubmission::class)->execute($form, $submission->id);

    $deletions = FormSubmissionFileDeletion::query()
        ->whereIn('path', [$oldFilePath, $currentFilePath])
        ->get();

    expect($deletions)->toHaveCount(2);

    $deletions->each(
        fn (FormSubmissionFileDeletion $deletion) =>
        (new DeletePendingSubmissionFile($deletion->id))->handle()
    );

    Storage::assertMissing($oldFilePath);
    Storage::assertMissing($currentFilePath);
    $this->assertDatabaseMissing('form_submission_files', ['form_submission_id' => $submission->id]);
    $this->assertDatabaseMissing('form_submission_file_deletions', ['path' => $oldFilePath]);
    $this->assertDatabaseMissing('form_submission_file_deletions', ['path' => $currentFilePath]);
});

it('tracks the original file when a legacy submission is updated for the first time', function () {
    Queue::fake();
    Storage::fake();

    $form = createRetentionForm($this);
    $fileFieldId = collect($form->properties)->firstWhere('type', 'files')['id'];
    $oldFileName = 'legacy-document.pdf';
    $newFileName = 'replacement-document.pdf';
    $oldFilePath = FileUploadPathService::getFileUploadPath($form->id, $oldFileName);
    $newFilePath = FileUploadPathService::getFileUploadPath($form->id, $newFileName);
    Storage::put($oldFilePath, 'legacy contents');
    Storage::put($newFilePath, 'replacement contents');

    $submission = FormSubmission::withoutEvents(fn () => $form->submissions()->create([
        'data' => [$fileFieldId => [$oldFileName]],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]));

    expect($submission->storedFiles()->count())->toBe(0);

    $submission->update(['data' => [$fileFieldId => [$newFileName]]]);

    expect($submission->storedFiles()
        ->whereIn('path', [$oldFilePath, $newFilePath])
        ->count())->toBe(2);

    app(DeleteFormSubmission::class)->execute($form, $submission->id);

    FormSubmissionFileDeletion::query()
        ->whereIn('path', [$oldFilePath, $newFilePath])
        ->get()
        ->each(
            fn (FormSubmissionFileDeletion $deletion) =>
            (new DeletePendingSubmissionFile($deletion->id))->handle()
        );

    Storage::assertMissing($oldFilePath);
    Storage::assertMissing($newFilePath);
});

it('keeps a retryable outbox entry when an uploaded file cannot be deleted', function () {
    Queue::fake();

    Storage::fake();
    $now = CarbonImmutable::parse('2026-07-20 12:00:00');
    $form = createRetentionForm($this);
    $fileFieldId = collect($form->properties)->firstWhere('type', 'files')['id'];
    $fileName = 'undeletable-document.pdf';
    $filePath = FileUploadPathService::getFileUploadPath($form->id, $fileName);
    $submission = createRetentionSubmission($form, $now->subDays(4), [
        $fileFieldId => [$fileName],
    ]);
    $submission->update(['data' => [$fileFieldId => [$fileName], 'edited' => true]]);

    app(DeleteFormSubmission::class)->execute($form, $submission->id);
    $deletion = FormSubmissionFileDeletion::where('path', $filePath)->firstOrFail();

    Storage::shouldReceive('exists')
        ->once()
        ->with($filePath)
        ->andReturnTrue();
    Storage::shouldReceive('delete')
        ->once()
        ->with($filePath)
        ->andReturnFalse();

    expect(fn () => (new DeletePendingSubmissionFile($deletion->id))->handle())
        ->toThrow(\RuntimeException::class, 'Unable to delete submission file');

    $this->assertDatabaseMissing('form_submissions', ['id' => $submission->id]);
    $this->assertDatabaseHas('form_submission_file_deletions', [
        'id' => $deletion->id,
        'attempts' => 1,
    ]);
});

it('keeps the submission and file when the surrounding database transaction rolls back', function () {
    Queue::fake();
    Storage::fake();

    $now = CarbonImmutable::parse('2026-07-20 12:00:00');
    $form = createRetentionForm($this);
    $fileFieldId = collect($form->properties)->firstWhere('type', 'files')['id'];
    $fileName = 'rollback-document.pdf';
    $filePath = FileUploadPathService::getFileUploadPath($form->id, $fileName);
    Storage::put($filePath, 'must remain stored');
    $submission = createRetentionSubmission($form, $now->subDays(4), [
        $fileFieldId => [$fileName],
    ]);

    expect(fn () => DB::transaction(function () use ($form, $submission) {
        app(DeleteFormSubmission::class)->execute($form, $submission->id);

        throw new RuntimeException('Abort surrounding operation');
    }))->toThrow(RuntimeException::class, 'Abort surrounding operation');

    $this->assertDatabaseHas('form_submissions', ['id' => $submission->id]);
    $this->assertDatabaseHas('form_submission_files', [
        'form_submission_id' => $submission->id,
        'path' => $filePath,
    ]);
    $this->assertDatabaseMissing('form_submission_file_deletions', ['path' => $filePath]);
    Storage::assertExists($filePath);
    Queue::assertNotPushed(DeletePendingSubmissionFile::class);
});

it('deletes files referenced by removed form properties', function () {
    Queue::fake();

    Storage::fake();
    $now = CarbonImmutable::parse('2026-07-20 12:00:00');
    $form = createRetentionForm($this);
    $fileProperty = collect($form->properties)->firstWhere('type', 'files');
    $fileName = 'removed-field-document.pdf';
    $filePath = FileUploadPathService::getFileUploadPath($form->id, $fileName);
    Storage::put($filePath, 'expired contents');
    $form->forceFill([
        'properties' => collect($form->properties)
            ->reject(fn ($property) => $property['id'] === $fileProperty['id'])
            ->values()
            ->all(),
        'removed_properties' => array_merge($form->removed_properties, [$fileProperty]),
    ])->saveQuietly();
    $submission = createRetentionSubmission($form, $now->subDays(4), [
        $fileProperty['id'] => [$fileName],
    ]);

    app(DeleteFormSubmission::class)->execute($form, $submission->id);
    $deletion = FormSubmissionFileDeletion::where('path', $filePath)->firstOrFail();
    (new DeletePendingSubmissionFile($deletion->id))->handle();

    Storage::assertMissing($filePath);
    $this->assertDatabaseMissing('form_submission_file_deletions', ['id' => $deletion->id]);
});

it('does not fail a committed deletion when cache invalidation fails', function () {
    $now = CarbonImmutable::parse('2026-07-20 12:00:00');
    $form = createRetentionForm($this);
    $submission = createRetentionSubmission($form, $now->subDays(4));
    $summaryService = Mockery::mock(FormSummaryService::class);
    $summaryService->shouldReceive('clearFormSummaryCache')
        ->once()
        ->andThrow(new \RuntimeException('Cache unavailable'));
    $deleteSubmission = new DeleteFormSubmission($summaryService);

    $deleted = $deleteSubmission->execute($form, $submission->id);

    expect($deleted)->toBeTrue();
    $this->assertDatabaseMissing('form_submissions', ['id' => $submission->id]);
});

it('processes more submissions than one deletion batch', function () {
    $now = CarbonImmutable::parse('2026-07-20 12:00:00');
    $form = createRetentionForm($this);

    foreach (range(1, 105) as $index) {
        createRetentionSubmission($form, $now->subDays(4), ['index' => $index]);
    }

    $summaryService = Mockery::mock(FormSummaryService::class);
    $summaryService->shouldReceive('clearFormSummaryCache')
        ->once()
        ->with(Mockery::on(fn (Form $cachedForm) => $cachedForm->is($form)));
    $retentionService = new SubmissionRetentionService(
        new DeleteFormSubmission($summaryService)
    );

    expect($retentionService->purge($form, $now))->toBe(105);
    expect($form->submissions()->count())->toBe(0);
});

it('supports a dry run without deleting expired submissions', function () {
    $now = CarbonImmutable::parse('2026-07-20 12:00:00');
    Carbon::setTestNow($now);
    $form = createRetentionForm($this);
    $submission = createRetentionSubmission($form, $now->subDays(4));

    $exitCode = Artisan::call('forms:purge-expired-submissions', [
        '--form' => $form->id,
        '--dry-run' => true,
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('1 submission(s) would be deleted');
    $this->assertDatabaseHas('form_submissions', ['id' => $submission->id]);
});

it('dispatches a purge job for a soft-deleted form', function () {
    Queue::fake();
    $now = CarbonImmutable::parse('2026-07-20 12:00:00');
    Carbon::setTestNow($now);
    $form = createRetentionForm($this);
    $submission = createRetentionSubmission($form, $now->subDays(4));
    $form->delete();

    Artisan::call('forms:purge-expired-submissions', ['--form' => $form->id]);

    Queue::assertPushed(
        PurgeExpiredFormSubmissionsJob::class,
        fn (PurgeExpiredFormSubmissionsJob $job) => $job->formId === $form->id
    );

    (new PurgeExpiredFormSubmissionsJob($form->id))
        ->handle(app(SubmissionRetentionService::class));

    $this->assertDatabaseMissing('form_submissions', ['id' => $submission->id]);
});

it('dispatches independent purge jobs for every configured form', function () {
    Queue::fake();
    $firstForm = createRetentionForm($this);
    $secondForm = $this->createForm(auth()->user(), $firstForm->workspace, [
        'submission_retention_value' => 3,
        'submission_retention_unit' => 'day',
    ]);

    $exitCode = Artisan::call('forms:purge-expired-submissions');

    expect($exitCode)->toBe(0);
    Queue::assertPushed(PurgeExpiredFormSubmissionsJob::class, 2);
    Queue::assertPushed(
        PurgeExpiredFormSubmissionsJob::class,
        fn (PurgeExpiredFormSubmissionsJob $job) => in_array($job->formId, [
            $firstForm->id,
            $secondForm->id,
        ], true)
    );
});

it('reloads the form policy once when a purge job starts', function () {
    $now = CarbonImmutable::parse('2026-07-20 12:00:00');
    Carbon::setTestNow($now);
    $form = createRetentionForm($this);
    $submission = createRetentionSubmission($form, $now->subDays(4));
    $job = new PurgeExpiredFormSubmissionsJob($form->id);
    $form->forceFill([
        'submission_retention_value' => null,
        'submission_retention_unit' => null,
    ])->saveQuietly();

    $job->handle(app(SubmissionRetentionService::class));

    $this->assertDatabaseHas('form_submissions', ['id' => $submission->id]);
});

it('redispatches stale pending file deletions', function () {
    Queue::fake();
    $deletion = FormSubmissionFileDeletion::create([
        'path' => 'forms/1/submissions/stale.pdf',
        'next_attempt_at' => now()->subHour(),
    ]);

    $exitCode = Artisan::call('forms:retry-pending-submission-file-deletions');
    $firstOutput = Artisan::output();
    $secondExitCode = Artisan::call('forms:retry-pending-submission-file-deletions');
    $secondOutput = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($secondExitCode)->toBe(0);
    expect($firstOutput)->toContain('Claimed 1 pending file deletion(s)');
    expect($secondOutput)->toContain('Claimed 0 pending file deletion(s)');
    Queue::assertPushed(DeletePendingSubmissionFile::class, 1);
    Queue::assertPushed(
        DeletePendingSubmissionFile::class,
        fn (DeletePendingSubmissionFile $job) =>
        $job->deletionId === $deletion->id
    );
});

it('rotates retry claims so older pending jobs cannot starve newer ones', function () {
    Queue::fake();
    $firstDeletion = FormSubmissionFileDeletion::create([
        'path' => 'forms/1/submissions/first-stale.pdf',
        'next_attempt_at' => now()->subHours(2),
    ]);
    $secondDeletion = FormSubmissionFileDeletion::create([
        'path' => 'forms/1/submissions/second-stale.pdf',
        'next_attempt_at' => now()->subHour(),
    ]);

    Artisan::call('forms:retry-pending-submission-file-deletions', ['--limit' => 1]);
    Artisan::call('forms:retry-pending-submission-file-deletions', ['--limit' => 1]);

    Queue::assertPushed(DeletePendingSubmissionFile::class, 2);
    Queue::assertPushed(
        DeletePendingSubmissionFile::class,
        fn (DeletePendingSubmissionFile $job) => $job->deletionId === $firstDeletion->id
    );
    Queue::assertPushed(
        DeletePendingSubmissionFile::class,
        fn (DeletePendingSubmissionFile $job) => $job->deletionId === $secondDeletion->id
    );
});
