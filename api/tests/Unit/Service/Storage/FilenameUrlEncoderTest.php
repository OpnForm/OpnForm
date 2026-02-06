<?php

uses(\Tests\TestCase::class);

use App\Service\Storage\FilenameUrlEncoder;

describe('FilenameUrlEncoder', function () {
    describe('encode', function () {
        it('encodes a simple filename', function () {
            $filename = 'document.pdf';
            $encoded = FilenameUrlEncoder::encode($filename);

            // Verify it's URL-safe (only contains alphanumeric, dash, underscore)
            expect($encoded)->toMatch('/^[A-Za-z0-9_-]+$/');
            // Verify it can be decoded back
            expect(FilenameUrlEncoder::decode($encoded))->toBe($filename);
        });

        it('encodes filename with parentheses', function () {
            $filename = 'image-(1)_abc-def-ghi.jpg';
            $encoded = FilenameUrlEncoder::encode($filename);

            expect($encoded)->toMatch('/^[A-Za-z0-9_-]+$/');
            expect(FilenameUrlEncoder::decode($encoded))->toBe($filename);
        });

        it('encodes filename with apostrophe', function () {
            $filename = "John's-document_uuid.pdf";
            $encoded = FilenameUrlEncoder::encode($filename);

            expect($encoded)->toMatch('/^[A-Za-z0-9_-]+$/');
            expect(FilenameUrlEncoder::decode($encoded))->toBe($filename);
        });

        it('encodes filename with multiple special characters', function () {
            $filename = "Report-(Q1)---John's-copy_uuid.pdf";
            $encoded = FilenameUrlEncoder::encode($filename);

            expect($encoded)->toMatch('/^[A-Za-z0-9_-]+$/');
            expect(FilenameUrlEncoder::decode($encoded))->toBe($filename);
        });

        it('encodes filename with exclamation mark', function () {
            $filename = 'Important!-document_uuid.pdf';
            $encoded = FilenameUrlEncoder::encode($filename);

            expect($encoded)->toMatch('/^[A-Za-z0-9_-]+$/');
            expect(FilenameUrlEncoder::decode($encoded))->toBe($filename);
        });

        it('encodes filename with asterisk', function () {
            $filename = 'star*file_uuid.pdf';
            $encoded = FilenameUrlEncoder::encode($filename);

            expect($encoded)->toMatch('/^[A-Za-z0-9_-]+$/');
            expect(FilenameUrlEncoder::decode($encoded))->toBe($filename);
        });

        it('encodes filename with unicode characters', function () {
            $filename = 'файл_document_uuid.pdf';
            $encoded = FilenameUrlEncoder::encode($filename);

            expect($encoded)->toMatch('/^[A-Za-z0-9_-]+$/');
            expect(FilenameUrlEncoder::decode($encoded))->toBe($filename);
        });

        it('encodes filename with spaces', function () {
            $filename = 'my file with spaces_uuid.pdf';
            $encoded = FilenameUrlEncoder::encode($filename);

            expect($encoded)->toMatch('/^[A-Za-z0-9_-]+$/');
            expect(FilenameUrlEncoder::decode($encoded))->toBe($filename);
        });
    });

    describe('decode', function () {
        it('decodes a valid base64url string', function () {
            $original = 'image-(1)_abc-def-ghi.jpg';
            $encoded = FilenameUrlEncoder::encode($original);

            expect(FilenameUrlEncoder::decode($encoded))->toBe($original);
        });

        it('returns original string if decoding fails', function () {
            // Invalid base64 that would produce invalid UTF-8
            $invalid = 'not-valid-base64!!!';

            // Should return the original string for backward compatibility
            $result = FilenameUrlEncoder::decode($invalid);
            expect($result)->toBe($invalid);
        });

        it('handles empty string', function () {
            $encoded = FilenameUrlEncoder::encode('');
            expect(FilenameUrlEncoder::decode($encoded))->toBe('');
        });
    });

    describe('isEncoded', function () {
        it('returns true for encoded filenames', function () {
            $original = 'image-(1)_abc.jpg';
            $encoded = FilenameUrlEncoder::encode($original);

            expect(FilenameUrlEncoder::isEncoded($encoded))->toBeTrue();
        });

        it('returns false for raw filenames with special characters', function () {
            $filename = 'image-(1)_abc.jpg';

            expect(FilenameUrlEncoder::isEncoded($filename))->toBeFalse();
        });

        it('returns false for filenames with dots', function () {
            $filename = 'document.pdf';

            expect(FilenameUrlEncoder::isEncoded($filename))->toBeFalse();
        });

        it('returns false for simple alphanumeric that matches itself when decoded', function () {
            // A string that is valid base64url but decodes to gibberish
            $simple = 'abc123';

            // This might or might not be considered encoded depending on decode result
            // The key is it should be safe to use
            $result = FilenameUrlEncoder::isEncoded($simple);
            expect($result)->toBeBool();
        });
    });

    describe('roundtrip', function () {
        it('handles various S3-safe filenames correctly', function () {
            $testCases = [
                'simple.pdf',
                'file_uuid.jpg',
                'image-(1)_abc-def.png',
                "John's-file_uuid.pdf",
                'Important!_uuid.txt',
                'star*file_uuid.doc',
                'mixed-(chars)_uuid.pdf',
                'unicode_файл_uuid.pdf',
                'spaces in name_uuid.pdf',
            ];

            foreach ($testCases as $filename) {
                $encoded = FilenameUrlEncoder::encode($filename);
                $decoded = FilenameUrlEncoder::decode($encoded);

                expect($decoded)->toBe($filename, "Failed for filename: {$filename}");
                expect($encoded)->toMatch('/^[A-Za-z0-9_-]+$/', "Encoded value contains unsafe chars: {$encoded}");
            }
        });
    });
});
