<?php

namespace App\Enterprise\Oidc;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OidcLinkService
{
    private const TOKEN_TTL_MINUTES = 15;

    public function createLinkToken(int $connectionId, string $subject, string $email, array $claims): string
    {
        $token = Str::random(64);

        Cache::put($this->cacheKey($token), [
            'connection_id' => $connectionId,
            'subject' => $subject,
            'email' => strtolower($email),
            'claims' => $claims,
        ], now()->addMinutes(self::TOKEN_TTL_MINUTES));

        return $token;
    }

    public function getLinkToken(string $token): ?array
    {
        $payload = Cache::get($this->cacheKey($token));

        return is_array($payload) ? $payload : null;
    }

    public function consumeLinkToken(string $token): ?array
    {
        $payload = Cache::pull($this->cacheKey($token));

        return is_array($payload) ? $payload : null;
    }

    private function cacheKey(string $token): string
    {
        return "oidc_link:{$token}";
    }
}
