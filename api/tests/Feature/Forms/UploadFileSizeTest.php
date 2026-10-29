<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('enforces the default local temporary upload limit at the byte boundary', function (int $bytes, int $status) {
    $file = UploadedFile::fake()->createWithContent('attachment.txt', 'safe content');
    $file->sizeToReport = $bytes;

    $response = $this->post('/upload-file', ['file' => $file]);

    $response->assertStatus($status);
    if ($status === 201) {
        Storage::assertExists($response->json('key'));
    } else {
        $response->assertJsonValidationErrors('file');
        expect(Storage::allFiles())->toBeEmpty();
    }
})->with([
    '50 MB accepted' => [50_000_000, 201],
    'one byte over 50 MB rejected' => [50_000_001, 422],
]);

it('supports a configured temporary upload limit', function (int $limit, int $bytes, int $status) {
    config(['opnform.public_uploads.max_file_size' => $limit]);
    $file = UploadedFile::fake()->createWithContent('attachment.txt', 'safe content');
    $file->sizeToReport = $bytes;

    $response = $this->post('/upload-file', ['file' => $file]);

    $response->assertStatus($status);
    if ($status === 422) {
        $response->assertJsonValidationErrors('file');
        expect(Storage::allFiles())->toBeEmpty();
    }
})->with([
    '100 MB accepted' => [100_000_000, 100_000_000, 201],
    'one byte over configured limit rejected' => [100_000_000, 100_000_001, 422],
    'lower configured limit accepted' => [1024, 1024, 201],
    'one byte over lower configured limit rejected' => [1024, 1025, 422],
]);

it('keeps workspace and field limits when submitting a local attachment larger than 5 MB', function (int $fieldLimit, bool $selfHosted, int $status) {
    config([
        'app.self_hosted' => $selfHosted,
        'cashier.key' => 'test-key',
        'plans.limits.file_upload_size.free' => 5_000_000,
    ]);
    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);
    expect($workspace->max_file_size)->toBe($selfHosted ? 50_000_000 : 5_000_000);
    $form = $this->createForm($user, $workspace, [
        'properties' => [[
            'id' => 'attachment',
            'name' => 'Attachment',
            'type' => 'files',
            'max_file_size' => $fieldLimit,
        ]],
    ]);

    $upload = $this->post('/upload-file', [
        'file' => UploadedFile::fake()->createWithContent('attachment.txt', str_repeat('a', 6_000_000)),
    ])->assertCreated();
    expect(Storage::size($upload->json('key')))->toBe(6_000_000);

    $response = $this->postJson(route('forms.answer', $form->slug), [
        'attachment' => ['attachment_' . $upload->json('uuid') . '.txt'],
    ]);

    $response->assertStatus($status);
    if ($status === 422) {
        $response->assertJsonValidationErrors('attachment.0');
        expect($form->submissions()->count())->toBe(0);
    } else {
        expect($form->submissions()->count())->toBe(1);
    }
})->with([
    '50 MB field accepts 6 MB' => [50, true, 200],
    '5 MB field rejects 6 MB' => [5, true, 422],
    '5 MB workspace rejects 6 MB despite a 50 MB field' => [50, false, 422],
]);

it('still rejects form assets larger than 5 MB after temporary upload', function () {
    $upload = $this->post('/upload-file', [
        'file' => UploadedFile::fake()->createWithContent('asset.txt', str_repeat('a', 6_000_000)),
    ])->assertCreated();

    $this->postJson('/open/forms/assets/upload', [
        'url' => 'asset_' . $upload->json('uuid') . '.txt',
        'type' => 'files',
    ])->assertUnprocessable()->assertJsonValidationErrors('url');
});
