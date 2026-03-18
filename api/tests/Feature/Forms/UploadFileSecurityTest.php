<?php

use App\Http\Controllers\Forms\FormController;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('rejects html uploads in the temp upload endpoint', function () {
    Storage::fake();

    $response = $this->post('/upload-file', [
        'file' => UploadedFile::fake()->createWithContent('evil.html', '<html><script>alert(1)</script></html>'),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

it('rejects xml uploads in the temp upload endpoint', function () {
    Storage::fake();

    $response = $this->post('/upload-file', [
        'file' => UploadedFile::fake()->createWithContent('evil.xml', '<?xml version="1.0"?><root><script/></root>'),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

it('sanitizes svg uploads before storing them in temporary storage', function () {
    Storage::fake();

    $response = $this->post('/upload-file', [
        'file' => UploadedFile::fake()->createWithContent('evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="5" height="5"/></svg>'),
    ]);

    $response->assertCreated();

    $storedContents = Storage::get($response->json('key'));
    expect($storedContents)
        ->not->toContain('<script')
        ->toContain('<svg');
});

it('rejects blocked active content when moving temp files to public assets', function () {
    Storage::fake();

    $uuid = (string) Str::uuid();
    Storage::put('tmp/' . $uuid, '<html><script>alert(1)</script></html>');

    $response = $this->postJson('/open/forms/assets/upload', [
        'url' => 'evil_' . $uuid . '.html',
        'type' => 'files',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('url');
});

it('sanitizes svg files when moving them to public assets', function () {
    Storage::fake();

    $uuid = (string) Str::uuid();
    Storage::put('tmp/' . $uuid, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="5" height="5"/></svg>');

    $response = $this->postJson('/open/forms/assets/upload', [
        'url' => 'evil_' . $uuid . '.svg',
        'type' => 'files',
    ]);

    $response->assertOk();

    $storedContents = Storage::get(FormController::ASSETS_UPLOAD_PATH . '/evil_' . $uuid . '.svg');
    expect($storedContents)
        ->not->toContain('<script')
        ->toContain('<svg');
});

it('throttles the unauthenticated upload endpoints', function () {
    Storage::fake();
    $this->withMiddleware(ThrottleRequests::class);
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);

    $uploadFileThrottled = false;
    for ($attempt = 0; $attempt < 15; $attempt++) {
        $response = $this->post('/upload-file', [
            'file' => UploadedFile::fake()->createWithContent('note-' . $attempt . '.txt', 'ok'),
        ]);

        if ($response->status() === 429) {
            $uploadFileThrottled = true;
            break;
        }

        $response->assertCreated();
    }
    expect($uploadFileThrottled)->toBeTrue();

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11']);

    $assetUploadThrottled = false;
    for ($attempt = 0; $attempt < 15; $attempt++) {
        $uuid = (string) Str::uuid();
        Storage::put('tmp/' . $uuid, 'safe file');

        $response = $this->postJson('/open/forms/assets/upload', [
            'url' => 'asset_' . $uuid . '.txt',
            'type' => 'files',
        ]);

        if ($response->status() === 429) {
            $assetUploadThrottled = true;
            break;
        }

        $response->assertOk();
    }
    expect($assetUploadThrottled)->toBeTrue();
});
