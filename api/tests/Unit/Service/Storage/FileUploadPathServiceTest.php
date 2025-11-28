<?php

uses(\Tests\TestCase::class);

use App\Service\Storage\FileUploadPathService;

describe('FileUploadPathService', function () {
    describe('getFileUploadPath', function () {
        it('returns correct path without filename', function () {
            $formId = 123;
            $path = FileUploadPathService::getFileUploadPath($formId);
            expect($path)->toBe('forms/123/submissions');
        });

        it('returns correct path with filename', function () {
            $formId = 456;
            $fileName = 'document.pdf';
            $path = FileUploadPathService::getFileUploadPath($formId, $fileName);
            expect($path)->toBe('forms/456/submissions/document.pdf');
        });

        it('handles string form ids', function () {
            $formId = 'abc123';
            $path = FileUploadPathService::getFileUploadPath($formId);
            expect($path)->toBe('forms/abc123/submissions');
        });

        it('throws exception for unsafe form id', function () {
            expect(fn () => FileUploadPathService::getFileUploadPath('../etc'))
                ->toThrow(\InvalidArgumentException::class, 'Path component contains invalid characters');
        });

        it('throws exception for unsafe filename', function () {
            expect(fn () => FileUploadPathService::getFileUploadPath(123, '../config.php'))
                ->toThrow(\InvalidArgumentException::class, 'Path component contains invalid characters');
        });
    });

    describe('getTmpFileUploadPath', function () {
        it('returns correct tmp path without filename', function () {
            $path = FileUploadPathService::getTmpFileUploadPath();
            expect($path)->toBe('tmp/');
        });

        it('returns correct tmp path with filename', function () {
            $uuid = '85e16d7b-58ed-43bc-8dce-7d3ff7d69f41';
            $path = FileUploadPathService::getTmpFileUploadPath($uuid);
            expect($path)->toBe('tmp/85e16d7b-58ed-43bc-8dce-7d3ff7d69f41');
        });

        it('throws exception for unsafe filename in tmp path', function () {
            expect(fn () => FileUploadPathService::getTmpFileUploadPath('../config.php'))
                ->toThrow(\InvalidArgumentException::class, 'Path component contains invalid characters');
        });
    });

    describe('path consistency', function () {
        it('ensures consistent directory separators in file paths', function () {
            // Create a path with an extra slash
            $formId = 789;
            $fileName = 'test.pdf';

            // Call the method with a form ID that should insert a trailing slash
            $path = FileUploadPathService::getFileUploadPath($formId, $fileName);

            // Path should not have double slashes
            expect($path)->not->toContain('//');
            expect($path)->toBe('forms/789/submissions/test.pdf');
        });
    });
});
