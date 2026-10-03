<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicMediaUrlRule implements ValidationRule
{
    private const PRIVATE_HOST_SUFFIXES = [
        'home',
        'internal',
        'lan',
        'local',
        'localhost',
        'test',
    ];

    private const TEMPORARY_HOST_SUFFIXES = [
        'loca.lt',
        'localtunnel.me',
        'ngrok-free.app',
        'ngrok.io',
        'trycloudflare.com',
    ];

    private const TEMPORARY_QUERY_PARAMETERS = [
        'expires',
        'signature',
        'x-amz-expires',
        'x-amz-signature',
        'x-goog-expires',
        'x-goog-signature',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail('The :attribute must be a valid public HTTPS URL.');

            return;
        }

        if (strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https') {
            $fail('The :attribute must use HTTPS.');

            return;
        }

        if (parse_url($value, PHP_URL_USER) !== null || parse_url($value, PHP_URL_PASS) !== null) {
            $fail('The :attribute cannot contain embedded credentials.');

            return;
        }

        $host = strtolower(rtrim(trim((string) parse_url($value, PHP_URL_HOST), '[]'), '.'));
        if ($host === '' || $this->isPrivateHost($host)) {
            $fail('The :attribute must use a public host.');

            return;
        }

        if ($this->hasSuffix($host, self::TEMPORARY_HOST_SUFFIXES)) {
            $fail('The :attribute cannot use a temporary tunnel host.');

            return;
        }

        parse_str((string) parse_url($value, PHP_URL_QUERY), $query);
        $queryKeys = array_map(
            fn (string|int $key): string => strtolower((string) $key),
            array_keys($query),
        );
        if (array_intersect($queryKeys, self::TEMPORARY_QUERY_PARAMETERS) !== []) {
            $fail('The :attribute cannot use an expiring or signed URL.');
        }
    }

    private function isPrivateHost(string $host): bool
    {
        if ($host === 'localhost' || $this->hasSuffix($host, self::PRIVATE_HOST_SUFFIXES)) {
            return true;
        }

        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }

    /**
     * @param  array<int, string>  $suffixes
     */
    private function hasSuffix(string $host, array $suffixes): bool
    {
        foreach ($suffixes as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }
}
