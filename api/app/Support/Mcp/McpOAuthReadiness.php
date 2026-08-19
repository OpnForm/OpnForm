<?php

namespace App\Support\Mcp;

final class McpOAuthReadiness
{
    public function __construct(private readonly McpOAuthKeyValidator $keys)
    {
    }

    /**
     * @return array{ready: bool, blockers: array<int, array{code: string, message: string}>}
     */
    public function inspect(): array
    {
        $blockers = [];

        if (! config('oauth.enabled', false)) {
            $blockers[] = [
                'code' => 'oauth_disabled',
                'message' => 'Delegated OAuth is disabled by OAUTH_ENABLED.',
            ];
        }

        foreach ([
            'APP_URL' => config('app.url'),
            'FRONT_URL' => config('app.front_url'),
        ] as $name => $url) {
            if (! $this->isSupportedPublicUrl($url)) {
                $blockers[] = [
                    'code' => strtolower($name).'_invalid',
                    'message' => "{$name} must be a valid HTTPS URL, or an HTTP loopback URL for local development.",
                ];
            }
        }

        $blockers = array_merge($blockers, $this->keys->blockers());

        return [
            'ready' => $blockers === [],
            'blockers' => $blockers,
        ];
    }

    private function isSupportedPublicUrl(mixed $value): bool
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if ($scheme === 'https') {
            return true;
        }

        $host = strtolower(trim((string) parse_url($value, PHP_URL_HOST), '[]'));

        return $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
