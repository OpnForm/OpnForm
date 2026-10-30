<?php

use App\Jobs\Form\StoreFormSubmissionJob;
use App\Rules\StorageFile;
use App\Service\Storage\FilenameUrlEncoder;
use App\Service\Storage\FileUploadPathService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('local');
    config()->set('app.url', 'http://localhost');
    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);
    $this->form = $this->createForm($user, $workspace, [
        'properties' => [['id' => 'attachment', 'name' => 'Attachment', 'type' => 'files']],
    ]);
    $this->fileName = 'cover_550e8400-e29b-41d4-a716-446655440000.png';
    Storage::put('assets/forms/' . $this->fileName, 'shared cover');
});

it('rejects shared asset URLs in anonymous submissions', function (string $kind) {
    $url = $kind === 'query host'
        ? 'https://attacker.example/' . $this->fileName . '?h=localhost'
        : route('forms.assets.show', $this->fileName);

    $this->assertGuest();
    $this->postJson(route('forms.answer', $this->form->slug), [
        'attachment' => [$url],
    ])->assertUnprocessable()->assertJsonValidationErrors('attachment.0');

    expect(Storage::get('assets/forms/' . $this->fileName))->toBe('shared cover');
    Storage::assertMissing(FileUploadPathService::getFileUploadPath($this->form->id, $this->fileName));
    expect($this->form->submissions()->count())->toBe(0);
})->with(['query host', 'real asset URL']);

it('never moves a shared asset even when the job is invoked without validation', function (string $kind) {
    $url = $kind === 'query host'
        ? 'https://attacker.example/' . $this->fileName . '?h=localhost'
        : route('forms.assets.show', $this->fileName);

    (new StoreFormSubmissionJob($this->form, ['attachment' => [$url], 'is_partial' => true]))->handle();

    expect(Storage::get('assets/forms/' . $this->fileName))->toBe('shared cover');
    Storage::assertMissing(FileUploadPathService::getFileUploadPath($this->form->id, $this->fileName));
    expect($this->form->submissions()->first()->data['attachment'])->toBe([]);
})->with(['query host', 'real asset URL']);

it('rejects untrusted existing-upload URLs in both validation and storage', function (string $kind) {
    $path = FileUploadPathService::getFileUploadPath($this->form->id, $this->fileName);
    Storage::put($path, 'existing upload');
    $parameters = [$this->form->id, FilenameUrlEncoder::encode($this->fileName)];
    $url = URL::publicSignedRoute('open.forms.submissions.file', $parameters);

    $url = match ($kind) {
        'unsigned' => strtok($url, '?'),
        'wrong host' => str_replace('http://localhost', 'http://attacker.example', $url),
        'host suffix' => str_replace('http://localhost', 'http://localhost.attacker.example', $url),
        'userinfo' => str_replace('http://localhost', 'http://localhost@attacker.example', $url),
        'wrong port' => str_replace('http://localhost', 'http://localhost:8888', $url),
        'wrong scheme' => str_replace('http://localhost', 'https://localhost', $url),
        'tampered' => $url . '&extra=1',
        'expired' => URL::publicSignedRoute('open.forms.submissions.file', $parameters, now()->subMinute()),
        'other form' => URL::publicSignedRoute('open.forms.submissions.file', [$this->form->id + 1, $parameters[1]]),
        'other route' => URL::publicSignedRoute('forms.assets.show', [$this->fileName]),
        'missing file' => URL::publicSignedRoute('open.forms.submissions.file', [$this->form->id, FilenameUrlEncoder::encode('missing.png')]),
    };

    expect((new StorageFile(1000000, [], $this->form))->passes('attachment', $url))->toBeFalse();
    (new StoreFormSubmissionJob($this->form, ['attachment' => [$url], 'is_partial' => true]))->handle();

    expect($this->form->submissions()->first()->data['attachment'])->toBe([]);
    expect(Storage::get($path))->toBe('existing upload');
    expect(Storage::get('assets/forms/' . $this->fileName))->toBe('shared cover');
})->with(['unsigned', 'wrong host', 'host suffix', 'userinfo', 'wrong port', 'wrong scheme', 'tampered', 'expired', 'other form', 'other route', 'missing file']);

it('retains a valid signed upload without moving or renaming it', function (string $fileName) {
    $path = FileUploadPathService::getFileUploadPath($this->form->id, $fileName);
    Storage::put($path, 'existing upload');
    $url = URL::publicSignedRoute('open.forms.submissions.file', [
        $this->form->id, FilenameUrlEncoder::encode($fileName),
    ], now()->addMinutes(10));

    expect((new StorageFile(1000000, [], $this->form))->passes('attachment', $url))->toBeTrue();
    (new StoreFormSubmissionJob($this->form, ['attachment' => [$url], 'is_partial' => true]))->handle();

    expect($this->form->submissions()->first()->data['attachment'])->toBe([$fileName]);
    expect(Storage::get($path))->toBe('existing upload');
})->with(['receipt_550e8400-e29b-41d4-a716-446655440000.png', "reçu (client's copy)_550e8400-e29b-41d4-a716-446655440000.pdf"]);

it('still moves a validated temporary upload into the form submissions', function () {
    $uuid = '550e8400-e29b-41d4-a716-446655440001';
    $fileName = 'receipt_' . $uuid . '.pdf';
    Storage::put(FileUploadPathService::getTmpFileUploadPath($uuid), 'uploaded PDF');

    expect((new StorageFile(1000000, ['pdf'], $this->form))->passes('attachment', $fileName))->toBeTrue();
    (new StoreFormSubmissionJob($this->form, ['attachment' => [$fileName], 'is_partial' => true]))->handle();

    Storage::assertMissing(FileUploadPathService::getTmpFileUploadPath($uuid));
    expect(Storage::get(FileUploadPathService::getFileUploadPath($this->form->id, $fileName)))->toBe('uploaded PDF');
});

it('copies the configured prefilled file for each anonymous submission', function (bool $multiple) {
    $url = route('forms.assets.show', $this->fileName);
    $this->form->update(['properties' => [[
        'id' => 'attachment', 'name' => 'Attachment', 'type' => 'files',
        'multiple' => $multiple, 'prefill' => $multiple ? [$url] : $url,
    ]]]);

    $this->assertGuest();
    for ($i = 0; $i < 2; $i++) {
        $this->postJson(route('forms.answer', $this->form->slug), [
            'attachment' => [$url],
        ])->assertSuccessful();
    }

    $names = $this->form->submissions()->get()->map(fn ($submission) => $submission->data['attachment'][0]);
    expect($names)->toHaveCount(2);
    expect($names->unique())->toHaveCount(2);
    foreach ($names as $name) {
        expect($name)->not->toBe($this->fileName);
        expect(Storage::get(FileUploadPathService::getFileUploadPath($this->form->id, $name)))->toBe('shared cover');
    }
    expect(Storage::get('assets/forms/' . $this->fileName))->toBe('shared cover');
})->with([false, true]);

it('copies configured prefills when the job bypasses request validation', function () {
    $url = route('forms.assets.show', $this->fileName);
    $this->form->update(['properties' => [[
        'id' => 'attachment', 'name' => 'Attachment', 'type' => 'files', 'prefill' => [$url],
    ]]]);

    (new StoreFormSubmissionJob($this->form, ['attachment' => [$url], 'is_partial' => true]))->handle();

    $name = $this->form->submissions()->first()->data['attachment'][0];
    expect($name)->not->toBe($this->fileName);
    expect(Storage::get(FileUploadPathService::getFileUploadPath($this->form->id, $name)))->toBe('shared cover');
    expect(Storage::get('assets/forms/' . $this->fileName))->toBe('shared cover');
});

it('does not authorize a prefilled asset from a different field', function () {
    $url = route('forms.assets.show', $this->fileName);
    $this->form->update(['properties' => [
        ['id' => 'attachment', 'name' => 'Attachment', 'type' => 'files'],
        ['id' => 'other', 'name' => 'Other file', 'type' => 'files', 'prefill' => [$url]],
    ]]);

    $this->postJson(route('forms.answer', $this->form->slug), [
        'attachment' => [$url],
    ])->assertUnprocessable()->assertJsonValidationErrors('attachment.0');

    (new StoreFormSubmissionJob($this->form, ['attachment' => [$url], 'is_partial' => true]))->handle();
    expect($this->form->submissions()->first()->data['attachment'])->toBe([]);
    expect(Storage::get('assets/forms/' . $this->fileName))->toBe('shared cover');
    Storage::assertMissing(FileUploadPathService::getFileUploadPath($this->form->id, $this->fileName));
});

it('reuses the prefilled copy through partial autosaves and completion', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $url = route('forms.assets.show', $this->fileName);
    $fieldId = '550e8400-e29b-41d4-a716-446655440002';
    $form = $this->createForm($user, $workspace, [
        'enable_partial_submissions' => true,
        'properties' => [['id' => $fieldId, 'name' => 'Attachment', 'type' => 'files', 'prefill' => [$url]]],
    ]);
    $this->actingAsGuest();

    $response = $this->postJson(route('forms.answer', $form->slug), [
        $fieldId => [$url], 'is_partial' => true,
    ])->assertSuccessful();
    $hash = $response->json('submission_hash');
    expect($hash)->not->toBeEmpty();
    $submission = $form->submissions()->first();
    $originalCopy = $submission->data[$fieldId][0];

    foreach ([true, true, false] as $isPartial) {
        $this->postJson(route('forms.answer', $form->slug), [
            $fieldId => [$url], 'is_partial' => $isPartial, 'submission_hash' => $hash,
        ])->assertSuccessful();
        expect($submission->fresh()->data[$fieldId])->toBe([$originalCopy]);
        expect(Storage::allFiles(FileUploadPathService::getFileUploadPath($form->id)))->toHaveCount(1);
    }

    expect($form->submissions()->count())->toBe(1);
    expect($submission->fresh()->status)->toBe(\App\Models\Forms\FormSubmission::STATUS_COMPLETED);
    expect(Storage::get('assets/forms/' . $this->fileName))->toBe('shared cover');
});

it('reuses the prefilled copy when matching an existing submission by field values', function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $url = route('forms.assets.show', $this->fileName);
    $form = $this->createForm($user, $workspace, [
        'database_fields_update' => ['email'],
        'properties' => [
            ['id' => 'email', 'name' => 'Email', 'type' => 'email'],
            ['id' => 'attachment', 'name' => 'Attachment', 'type' => 'files', 'prefill' => [$url]],
        ],
    ]);

    for ($i = 0; $i < 3; $i++) {
        (new StoreFormSubmissionJob($form, ['email' => 'respondent@example.test', 'attachment' => [$url]]))->handle();
    }

    expect($form->submissions()->count())->toBe(1);
    expect(Storage::allFiles(FileUploadPathService::getFileUploadPath($form->id)))->toHaveCount(1);
    expect(Storage::get('assets/forms/' . $this->fileName))->toBe('shared cover');
});
