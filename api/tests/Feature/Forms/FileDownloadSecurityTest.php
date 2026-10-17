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
    $signedUrl = URL::signedRoute(
        'open.forms.submissions.file',
        [$form->id, $encodedFilename],
        absolute: false
    );
    $response = $this->get($signedUrl);

    $response->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toStartWith('attachment;');
    expect(strtolower($response->headers->get('content-type')))
        ->toBe('application/octet-stream');
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
    $signedUrl = URL::signedRoute(
        'open.forms.submissions.file',
        [$form->id, $encodedFilename],
        absolute: false
    );

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->get($signedUrl)->assertOk();

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->map(fn (string $sql) => strtolower($sql));

    expect($queries->contains(fn (string $sql) => str_contains($sql, 'user_workspace')))->toBeFalse();
});


it('keeps signed attachment requests separate from the general API quota', function () {
    $this->withMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    $router = app('router');
    $middleware = $router->getMiddlewareGroups()['api'];
    array_unshift($middleware, 'throttle:100,1');
    $router->middlewareGroup('api', $middleware);

    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    Storage::fake();
    $fileName = 'attachment.heic';
    Storage::put(FileUploadPathService::getFileUploadPath($form->id, $fileName), 'image bytes');
    $url = URL::temporarySignedRoute('open.forms.submissions.file', now()->addHour(), [
        $form->id, FilenameUrlEncoder::encode($fileName),
    ], absolute: false);

    // A real page with 100 rows and multiple photos can exceed 100 previews.
    for ($i = 0; $i < 175; $i++) {
        $this->get($url)->assertOk();
    }
    for ($i = 0; $i < 100; $i++) {
        $this->getJson(route('open.forms.submissions.index', ['form' => $form]))->assertSuccessful();
    }
    $this->getJson(route('open.forms.submissions.index', ['form' => $form]))->assertStatus(429);
    $this->get($url)->assertOk();

    // Raising attachment capacity must not remove expiry/signature checks.
    $expired = URL::temporarySignedRoute('open.forms.submissions.file', now()->subMinute(), [
        $form->id, FilenameUrlEncoder::encode($fileName),
    ], absolute: false);
    $this->get($expired)->assertForbidden();
    $this->get(strtok($url, '?'))->assertForbidden();
});

it('still rate limits signed attachment downloads per form and client', function () {
    $this->withMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);
    Storage::fake();
    $fileName = 'attachment.jpg';
    Storage::put(FileUploadPathService::getFileUploadPath($form->id, $fileName), 'image bytes');
    $url = URL::temporarySignedRoute('open.forms.submissions.file', now()->addHour(), [
        $form->id, FilenameUrlEncoder::encode($fileName),
    ], absolute: false);

    for ($i = 0; $i < 600; $i++) {
        $this->get($url)->assertOk();
    }
    $this->get($url)->assertStatus(429)->assertHeader('Retry-After');

    $otherForm = $this->createForm($user, $workspace);
    Storage::put(FileUploadPathService::getFileUploadPath($otherForm->id, $fileName), 'image bytes');
    $otherUrl = URL::temporarySignedRoute('open.forms.submissions.file', now()->addHour(), [
        $otherForm->id, FilenameUrlEncoder::encode($fileName),
    ], absolute: false);
    $this->get($otherUrl)->assertOk();
});
