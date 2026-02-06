<?php

namespace App\Service\Storage;

/**
 * Service for URL-safe encoding/decoding of filenames.
 *
 * This is needed because filenames with special characters (like parentheses, apostrophes)
 * cause issues with Laravel signed URLs when the web server decodes the URL path
 * before passing it to PHP. Using base64url encoding ensures the filename only
 * contains URL-safe characters that don't get decoded by the web server.
 *
 * @see https://github.com/OpnForm/OpnForm/issues/1024
 */
class FilenameUrlEncoder
{
    /**
     * Encode a filename using URL-safe base64 encoding.
     *
     * Uses base64url encoding (RFC 4648 §5) which replaces:
     * - '+' with '-'
     * - '/' with '_'
     * - Removes padding '='
     *
     * @param string $filename The original filename
     * @return string The URL-safe encoded filename
     */
    public static function encode(string $filename): string
    {
        return rtrim(strtr(base64_encode($filename), '+/', '-_'), '=');
    }

    /**
     * Decode a URL-safe base64 encoded filename.
     *
     * @param string $encoded The encoded filename
     * @return string The original filename
     */
    public static function decode(string $encoded): string
    {
        // Add padding back if needed
        $padded = str_pad(strtr($encoded, '-_', '+/'), strlen($encoded) + (4 - strlen($encoded) % 4) % 4, '=', STR_PAD_RIGHT);

        $decoded = base64_decode($padded, true);

        if ($decoded === false) {
            // If decoding fails, return the original string (backward compatibility)
            return $encoded;
        }

        return $decoded;
    }

    /**
     * Check if a string appears to be base64url encoded.
     *
     * This helps with backward compatibility - if a filename doesn't look
     * like it's encoded, we can handle it as a raw filename.
     *
     * @param string $value The value to check
     * @return bool True if the value appears to be base64url encoded
     */
    public static function isEncoded(string $value): bool
    {
        // Base64url only contains alphanumeric, dash, and underscore
        // Real filenames typically have dots and other characters
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            return false;
        }

        // Try to decode and check if result is valid UTF-8
        $decoded = self::decode($value);

        // If decoded equals input, it wasn't really encoded
        if ($decoded === $value) {
            return false;
        }

        // Check if the decoded value is valid UTF-8
        return mb_check_encoding($decoded, 'UTF-8');
    }
}
