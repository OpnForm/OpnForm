<?php

use App\Exceptions\UploadSecurityException;
use App\Service\Storage\UploadSecurityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

it('rejects blocked html files by extension', function () {
    $service = new UploadSecurityService();

    expect(fn () => $service->inspectUploadedFile(
        UploadedFile::fake()->createWithContent('evil.html', 'plain text')
    ))->toThrow(UploadSecurityException::class);
});

it('allows non-image files that are not active content', function () {
    $service = new UploadSecurityService();

    $inspection = $service->inspectUploadedFile(
        UploadedFile::fake()->createWithContent('notes.txt', 'hello world')
    );

    expect($inspection->isImage)->toBeFalse();
    expect($inspection->isSvg)->toBeFalse();
});

it('sanitizes svg uploads', function () {
    $service = new UploadSecurityService();

    $inspection = $service->inspectUploadedFile(
        UploadedFile::fake()->createWithContent('evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>')
    );

    expect($inspection->isSvg)->toBeTrue();
    expect($inspection->sanitizedContents)
        ->not->toContain('<script')
        ->toContain('<svg');
});

it('uses detected mime when the extension is misleading', function () {
    Storage::fake();

    $service = new UploadSecurityService();
    Storage::put('tmp/mismatch', '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>');

    $inspection = $service->inspectStoredFile('tmp/mismatch', 'not-really.txt');

    expect($inspection->mimeType)->toBe('image/svg+xml');
    expect($inspection->isSvg)->toBeTrue();
});
