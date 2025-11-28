<?php

use App\Service\Storage\FileUploadPathService;
use App\Service\Storage\StorageFileNameParser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function () {
    Storage::clearResolvedInstances();
    \Mockery::close();
});

it('can validate file types', function () {
    Storage::fake();

    $validator = new \App\Rules\StorageFile(1000000, ['jpg', 'JPEG', 'png']);

    // Create valid files in fake storage
    collect([
        'file_name_' . Str::uuid() . '.jpg',
        'file_name_' . Str::uuid() . '.png',
        'file_name_' . Str::uuid() . '.JPG',
        'file_name_' . Str::uuid() . '.JPEG'
    ])->each(function ($file) use ($validator) {
        // Extract UUID from filename
        $parser = StorageFileNameParser::parse($file);
        $uuid = $parser->uuid;

        // Create file in fake storage at tmp/{uuid} path
        $path = FileUploadPathService::getTmpFileUploadPath($uuid);
        Storage::put($path, str_repeat('x', 100)); // Small file

        $this->assertTrue($validator->passes('file', $file));
    });

    // Test invalid file type
    $invalidFile = 'file_name_' . Str::uuid() . '.pdf';
    $parser = StorageFileNameParser::parse($invalidFile);
    $uuid = $parser->uuid;
    $path = FileUploadPathService::getTmpFileUploadPath($uuid);
    Storage::put($path, str_repeat('x', 100));

    $this->assertFalse($validator->passes('file', $invalidFile));
});

it('can validate file size', function () {
    Storage::fake();

    $validator = new \App\Rules\StorageFile(1000000);

    // Create file with valid size (1MB)
    $validFile = 'file_name_' . Str::uuid() . '.jpg';
    $parser = StorageFileNameParser::parse($validFile);
    $uuid = $parser->uuid;
    $path = FileUploadPathService::getTmpFileUploadPath($uuid);
    Storage::put($path, str_repeat('x', 1000000)); // Exactly 1MB

    $this->assertTrue($validator->passes('file', $validFile));

    // Create file with invalid size (2MB, exceeds limit)
    $invalidFile = 'file_name_' . Str::uuid() . '.pdf';
    $parser = StorageFileNameParser::parse($invalidFile);
    $uuid = $parser->uuid;
    $path = FileUploadPathService::getTmpFileUploadPath($uuid);
    Storage::put($path, str_repeat('x', 2000000)); // 2MB file

    $this->assertFalse($validator->passes('file', $invalidFile));
});
