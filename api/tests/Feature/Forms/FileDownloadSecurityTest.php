<?php

use App\Models\Forms\FormSubmission;
use App\Service\Storage\FileUploadPathService;
use App\Service\Storage\FilenameUrlEncoder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

it('forces attachment download with safe headers for submission files', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    // Fake local storage
    Storage::fake();

    $fileName = 'test_file_' . uniqid() . '.svg';
    $path = FileUploadPathService::getFileUploadPath($form->id, $fileName);
    Storage::put($path, '<svg><script>alert(1)</script></svg>');

    /** @var FormSubmission $submission */
    $submission = $form->submissions()->create([
        'form_id' => $form->id,
        'data' => [
            'files_field' => [$fileName],
        ],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);

    // Call signed file route with encoded filename (as the new implementation does)
    // See: https://github.com/OpnForm/OpnForm/issues/1024
    $encodedFilename = FilenameUrlEncoder::encode($fileName);
    $signedUrl = URL::signedRoute('open.forms.submissions.file', [$form->id, $encodedFilename]);
    $response = $this->get($signedUrl);

    $response->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toStartWith('attachment;');
    expect(strtolower($response->headers->get('content-type')))
        ->toBe('application/octet-stream');
    expect($response->headers->get('x-content-type-options'))
        ->toBe('nosniff');
});

it('serves generated signatures inline with a safe image content type', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    $signatureId = 'signature_' . uniqid();
    $form->properties = array_merge($form->properties, [
        ['id' => $signatureId, 'name' => 'Signature', 'type' => 'signature'],
    ]);
    $form->save();

    Storage::fake();

    $fileName = 'sign_' . uniqid() . '.png';
    Storage::put(
        FileUploadPathService::getFileUploadPath($form->id, $fileName),
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAgMBAQEAAP8AAAAASUVORK5CYII=', true)
    );

    $form->submissions()->create([
        'form_id' => $form->id,
        'data' => [$signatureId => $fileName],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);

    $submissions = $this->getJson(route('open.forms.submissions.index', [$form->id]))
        ->assertOk();
    $signatureUrl = $submissions->json('data.0.data.' . $signatureId . '.0.file_url');

    expect($signatureUrl)->toContain('inline=1');

    $response = $this->get($signatureUrl)->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toStartWith('inline;');
    expect(strtolower($response->headers->get('content-type')))
        ->toStartWith('image/png');
    expect($response->headers->get('x-content-type-options'))
        ->toBe('nosniff');
});

it('does not eager load workspace users for signed submission file downloads', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $extraUser = \App\Models\User::factory()->create();
    $workspace->users()->attach($extraUser->id, ['role' => 'user']);

    Storage::fake();

    $fileName = 'test_file_' . uniqid() . '.pdf';
    $path = FileUploadPathService::getFileUploadPath($form->id, $fileName);
    Storage::put($path, 'test pdf content');

    $form->submissions()->create([
        'form_id' => $form->id,
        'data' => [
            'files_field' => [$fileName],
        ],
        'status' => FormSubmission::STATUS_COMPLETED,
    ]);

    $encodedFilename = FilenameUrlEncoder::encode($fileName);
    $signedUrl = URL::signedRoute('open.forms.submissions.file', [$form->id, $encodedFilename]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->get($signedUrl)->assertOk();

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->map(fn (string $sql) => strtolower($sql));

    expect($queries->contains(fn (string $sql) => str_contains($sql, 'user_workspace')))->toBeFalse();
});
