<?php

use App\Models\Forms\FormSubmission;
use App\Service\Storage\FileUploadPathService;
use App\Service\Storage\FilenameUrlEncoder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

describe('File download with special characters in filename', function () {
    it('downloads files with parentheses in filename using encoded URL', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        Storage::fake();

        // Filename with parentheses - simulates "image (1).jpg" after S3KeyCleaner sanitization
        $uuid = Str::uuid()->toString();
        $fileName = "image-(1)_{$uuid}.jpg";
        $path = FileUploadPathService::getFileUploadPath($form->id, $fileName);
        Storage::put($path, 'test image content');

        $form->submissions()->create([
            'form_id' => $form->id,
            'data' => [
                'files_field' => [$fileName],
            ],
            'status' => FormSubmission::STATUS_COMPLETED,
        ]);

        // Use base64url encoded filename (as the new implementation does)
        $encodedFilename = FilenameUrlEncoder::encode($fileName);
        $signedUrl = URL::signedRoute('open.forms.submissions.file', [$form->id, $encodedFilename]);
        $response = $this->get($signedUrl);

        $response->assertOk();
    });

    it('downloads files with spaces converted to dashes in filename using encoded URL', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        Storage::fake();

        // Filename with dashes (from original spaces) - simulates "my file.pdf" after S3KeyCleaner sanitization
        $uuid = Str::uuid()->toString();
        $fileName = "my-file_{$uuid}.pdf";
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
        $response = $this->get($signedUrl);

        $response->assertOk();
    });

    it('downloads files with apostrophe in filename using encoded URL', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        Storage::fake();

        // Filename with apostrophe - "John's-document.pdf"
        $uuid = Str::uuid()->toString();
        $fileName = "John's-document_{$uuid}.pdf";
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
        $response = $this->get($signedUrl);

        $response->assertOk();
    });

    it('downloads files with multiple special characters in filename using encoded URL', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        Storage::fake();

        // Filename with multiple special characters - "Report (Q1) - John's copy.pdf"
        // After S3KeyCleaner: "Report-(Q1)---John's-copy.pdf" (spaces → dashes)
        $uuid = Str::uuid()->toString();
        $fileName = "Report-(Q1)---John's-copy_{$uuid}.pdf";
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
        $response = $this->get($signedUrl);

        $response->assertOk();
    });

    it('downloads files with exclamation mark in filename using encoded URL', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        Storage::fake();

        $uuid = Str::uuid()->toString();
        $fileName = "Important!-document_{$uuid}.pdf";
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
        $response = $this->get($signedUrl);

        $response->assertOk();
    });

    it('downloads files with asterisk in filename using encoded URL', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        Storage::fake();

        $uuid = Str::uuid()->toString();
        $fileName = "star*file_{$uuid}.pdf";
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
        $response = $this->get($signedUrl);

        $response->assertOk();
    });
});
