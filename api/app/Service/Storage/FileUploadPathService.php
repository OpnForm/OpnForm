<?php

namespace App\Service\Storage;

use Illuminate\Support\Str;

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
