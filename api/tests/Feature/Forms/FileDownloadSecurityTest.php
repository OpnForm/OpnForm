<?php

use App\Models\Forms\FormSubmission;
use App\Service\Storage\FileUploadPathService;
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

    // Call signed file route (route is protected by 'signed' middleware)
    $signedUrl = URL::signedRoute('open.forms.submissions.file', [$form->id, $fileName]);
    $response = $this->get($signedUrl);

    $response->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toStartWith('attachment;');
    expect(strtolower($response->headers->get('content-type')))
        ->toBe('application/octet-stream');
    expect($response->headers->get('x-content-type-options'))
        ->toBe('nosniff');
});
