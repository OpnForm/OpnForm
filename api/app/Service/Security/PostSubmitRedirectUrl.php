<?php

namespace App\Service\Security;

class PostSubmitRedirectUrl
{
    /**
     * @return array{url: string, external: bool}|null
     */
    public static function parse(?string $url): ?array
    {
        if (!is_string($url) || trim($url) === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if (parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            return null;
        }

        return [
            'url' => $url,
            'external' => !self::isSameOrigin($url),
        ];
    }

    private static function isSameOrigin(string $url): bool
    {
        $targetHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        $targetScheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        foreach (self::trustedOrigins() as $origin) {
            if (
                $targetHost === strtolower((string) parse_url($origin, PHP_URL_HOST)) &&
                $targetScheme === strtolower((string) parse_url($origin, PHP_URL_SCHEME))
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private static function trustedOrigins(): array
    {
        return array_values(array_filter(array_unique([
            request()->getSchemeAndHttpHost(),
            config('app.url'),
            front_url('/'),
        ])));
    }
}
