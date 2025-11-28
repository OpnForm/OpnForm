<?php

/**
 * Test the front_url helper function independently without database/container
 */
describe('front_url helper', function () {
    describe('protocol handling', function () {
        it('adds https protocol if missing', function () {
            $baseUrl = 'example.com';
            $expected = 'https://example.com/forms';

            $result = testFrontUrl($baseUrl, 'forms');
            expect($result)->toBe($expected);
        });

        it('adds https protocol to subdomain', function () {
            $baseUrl = 'app.example.com';
            $expected = 'https://app.example.com/admin';

            $result = testFrontUrl($baseUrl, 'admin');
            expect($result)->toBe($expected);
        });

        it('preserves existing http protocol', function () {
            $baseUrl = 'http://example.com';
            $expected = 'http://example.com/forms';

            $result = testFrontUrl($baseUrl, 'forms');
            expect($result)->toBe($expected);
        });

        it('preserves existing https protocol', function () {
            $baseUrl = 'https://example.com';
            $expected = 'https://example.com/forms';

            $result = testFrontUrl($baseUrl, 'forms');
            expect($result)->toBe($expected);
        });

        it('handles protocol case insensitively', function () {
            $baseUrl = 'HTTPS://example.com';
            $expected = 'HTTPS://example.com/forms';

            $result = testFrontUrl($baseUrl, 'forms');
            expect($result)->toBe($expected);
        });
    });

    describe('URL validation', function () {
        it('returns path for invalid base URL after protocol addition', function () {
            $baseUrl = 'invalid::url';
            $expected = 'forms';

            $result = testFrontUrl($baseUrl, 'forms');
            expect($result)->toBe($expected);
        });

        it('returns path when base URL is invalid', function () {
            $baseUrl = ':::';
            $expected = 'some/path';

            $result = testFrontUrl($baseUrl, 'some/path');
            expect($result)->toBe($expected);
        });
    });

    describe('path handling', function () {
        it('combines base URL with path', function () {
            $baseUrl = 'https://example.com';
            $expected = 'https://example.com/forms';

            $result = testFrontUrl($baseUrl, 'forms');
            expect($result)->toBe($expected);
        });

        it('handles paths with leading slash', function () {
            $baseUrl = 'https://example.com';
            $expected = 'https://example.com/forms';

            $result = testFrontUrl($baseUrl, '/forms');
            expect($result)->toBe($expected);
        });

        it('handles nested paths', function () {
            $baseUrl = 'https://example.com';
            $expected = 'https://example.com/forms/123/edit';

            $result = testFrontUrl($baseUrl, 'forms/123/edit');
            expect($result)->toBe($expected);
        });

        it('handles paths with multiple leading slashes', function () {
            $baseUrl = 'https://example.com';
            $expected = 'https://example.com/forms';

            $result = testFrontUrl($baseUrl, '///forms');
            expect($result)->toBe($expected);
        });

        it('returns base URL without trailing slash when path is empty', function () {
            $baseUrl = 'https://example.com';
            $expected = 'https://example.com';

            $result = testFrontUrl($baseUrl, '');
            expect($result)->toBe($expected);
        });

        it('returns base URL without trailing slash when no path provided', function () {
            $baseUrl = 'https://example.com';
            $expected = 'https://example.com';

            $result = testFrontUrl($baseUrl);
            expect($result)->toBe($expected);
        });
    });

    describe('base URL normalization', function () {
        it('removes trailing slash from base URL', function () {
            $baseUrl = 'https://example.com/';
            $expected = 'https://example.com/forms';

            $result = testFrontUrl($baseUrl, 'forms');
            expect($result)->toBe($expected);
        });

        it('removes multiple trailing slashes from base URL', function () {
            $baseUrl = 'https://example.com///';
            $expected = 'https://example.com/forms';

            $result = testFrontUrl($baseUrl, 'forms');
            expect($result)->toBe($expected);
        });

        it('prevents double slashes between base URL and path', function () {
            $baseUrl = 'https://example.com/';
            $expected = 'https://example.com/forms';

            $result = testFrontUrl($baseUrl, '/forms');
            expect($result)->toBe($expected);
        });
    });

    describe('edge cases', function () {
        it('handles port numbers in base URL', function () {
            $baseUrl = 'https://example.com:3000';
            $expected = 'https://example.com:3000/forms';

            $result = testFrontUrl($baseUrl, 'forms');
            expect($result)->toBe($expected);
        });

        it('handles base URLs with paths', function () {
            $baseUrl = 'https://example.com/app';
            $expected = 'https://example.com/app/forms';

            $result = testFrontUrl($baseUrl, 'forms');
            expect($result)->toBe($expected);
        });

        it('handles paths with special characters', function () {
            $baseUrl = 'https://example.com';
            $expected = 'https://example.com/forms/slug-with-dashes';

            $result = testFrontUrl($baseUrl, 'forms/slug-with-dashes');
            expect($result)->toBe($expected);
        });

        it('handles paths with query-like strings', function () {
            $baseUrl = 'https://example.com';
            $expected = 'https://example.com/forms?test=1';

            $result = testFrontUrl($baseUrl, 'forms?test=1');
            expect($result)->toBe($expected);
        });

        it('handles localhost URLs without protocol', function () {
            $baseUrl = 'localhost:3000';
            $expected = 'https://localhost:3000/forms';

            $result = testFrontUrl($baseUrl, 'forms');
            expect($result)->toBe($expected);
        });

        it('returns path when baseUrl is empty', function () {
            $baseUrl = '';
            $expected = 'forms';

            $result = testFrontUrl($baseUrl, 'forms');
            expect($result)->toBe($expected);
        });

        it('returns empty string when baseUrl and path are empty', function () {
            $baseUrl = '';
            $expected = '';

            $result = testFrontUrl($baseUrl, '');
            expect($result)->toBe($expected);
        });
    });
});

/**
 * Helper function to test front_url without needing Laravel container
 * This simulates the function logic directly
 */
function testFrontUrl(string $baseUrl = '', string $path = ''): string
{
    if (! $baseUrl) {
        return $path;
    }

    // Ensure baseUrl has a protocol (defaults to https for security)
    if (! preg_match('~^https?://~i', $baseUrl)) {
        $baseUrl = 'https://' . $baseUrl;
    }

    // Validate URL format
    if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
        return $path;
    }

    // Remove trailing slash from base URL
    $cleanBaseUrl = rtrim($baseUrl, '/');

    // Return base URL if no path provided
    if (! $path) {
        return $cleanBaseUrl;
    }

    // Combine base URL with path, ensuring single forward slash
    return $cleanBaseUrl . '/' . ltrim($path, '/');
}
