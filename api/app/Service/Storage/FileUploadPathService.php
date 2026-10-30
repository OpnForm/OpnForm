<?php

namespace App\Service\Storage;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Service for managing file upload paths in the application
 *
 * This service centralizes all path generation for file uploads, ensuring
 * consistent path structures throughout the application.
 */
class FileUploadPathService
{
    /**
     * Base path for form submission file uploads
     */
    private const FILE_UPLOAD_PATH = 'forms/?/submissions';

    /**
     * Base path for temporary file uploads
     */
    private const TMP_FILE_UPLOAD_PATH = 'tmp/';

    /** Resolve only signed download URLs for an existing upload in this form. */
    public static function resolveSubmissionFileUrl(string $value, int|string $formId): ?string
    {
        if (!self::hasApplicationOrigin($value)) {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH) ?? '';
        $segment = rawurldecode(basename($path));
        $expectedPath = route('open.forms.submissions.file', [$formId, $segment], false);
        if ($path !== $expectedPath || !URL::hasValidSignature(Request::create($value), absolute: false)) {
            return null;
        }

        $fileName = FilenameUrlEncoder::isEncoded($segment) ? FilenameUrlEncoder::decode($segment) : $segment;
        if ($fileName === '' || in_array($fileName, ['.', '..'], true)
            || str_contains($fileName, '/') || str_contains($fileName, '\\') || str_contains($fileName, "\0")) {
            return null;
        }

        return Storage::exists(self::getFileUploadPath($formId, $fileName)) ? $fileName : null;
    }

    /** Only the persisted prefill for this field can authorize a shared asset copy. */
    public static function resolvePrefilledAssetUrl(string $value, ?array $field): ?string
    {
        if (($field['type'] ?? null) !== 'files'
            || !in_array($value, (array) ($field['prefill'] ?? []), true)
            || !self::hasApplicationOrigin($value)) {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH) ?? '';
        $fileName = rawurldecode(basename($path));
        if (parse_url($value, PHP_URL_QUERY) !== null
            || $path !== route('forms.assets.show', [$fileName], false)
            || str_contains($fileName, '/') || str_contains($fileName, '\\')
            || !StorageFileNameParser::parse($fileName)->uuid) {
            return null;
        }

        return Storage::exists('assets/forms/' . $fileName) ? $fileName : null;
    }

    private static function hasApplicationOrigin(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($value);
        $origin = parse_url(config('app.url') ?? '');
        if (!is_array($parts) || !is_array($origin)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || strtolower($parts['scheme'] ?? '') !== strtolower($origin['scheme'] ?? '')
            || strtolower($parts['host'] ?? '') !== strtolower($origin['host'] ?? '')
            || ($parts['port'] ?? null) !== ($origin['port'] ?? null)) {
            return false;
        }
        return true;
    }

    /**
     * Generate the file upload path for a specific form
     *
     * @param int|string $formId The form ID
     * @param string|null $fileName Optional filename to append
     * @return string The complete file upload path
     *
     * @example FileUploadPathService::getFileUploadPath(123) // returns "forms/123/submissions"
     * @example FileUploadPathService::getFileUploadPath(123, "document.pdf") // returns "forms/123/submissions/document.pdf"
     */
    public static function getFileUploadPath(int|string $formId, ?string $fileName = null): string
    {
        // Validate form ID to prevent path traversal attacks
        self::validatePathComponent($formId);

        $path = Str::of(self::FILE_UPLOAD_PATH)->replace('?', $formId);

        if ($fileName) {
            // Validate filename
            self::validatePathComponent($fileName);

            // Ensure consistent directory separator
            $path = Str::finish($path, '/') . $fileName;
        }

        return $path;
    }

    /**
     * Generate the temporary file upload path
     *
     * @param string|null $fileName Optional filename to append
     * @return string The complete temporary file upload path
     *
     * @example FileUploadPathService::getTmpFileUploadPath() // returns "tmp/"
     * @example FileUploadPathService::getTmpFileUploadPath("abc123") // returns "tmp/abc123"
     */
    public static function getTmpFileUploadPath(?string $fileName = null): string
    {
        $path = self::TMP_FILE_UPLOAD_PATH;

        if ($fileName) {
            // Validate filename
            self::validatePathComponent($fileName);

            // Ensure consistent directory separator
            $path = Str::finish($path, '/') . $fileName;
        }

        return $path;
    }

    /**
     * Validates a path component to prevent path traversal attacks
     *
     * @param string|int $component The path component to validate
     * @throws \InvalidArgumentException If the path component contains invalid characters
     */
    private static function validatePathComponent(string|int $component): void
    {
        $component = (string) $component;

        // Check for path traversal attempts or problematic characters
        if (
            str_contains($component, '/') ||
            str_contains($component, '\\')
        ) {
            throw new \InvalidArgumentException('Path component contains invalid characters');
        }
    }
}
