<?php

namespace App\Integrations\OAuth\Drivers;

use App\Exceptions\OAuth\InvalidWidgetDataException;
use App\Integrations\OAuth\Drivers\Contracts\WidgetOAuthDriver;
use Laravel\Socialite\Contracts\User;

class OAuthTelegramDriver implements WidgetOAuthDriver
{
    protected string $redirectUrl;
    protected array $scopes = [];

    public function getRedirectUrl(): string
    {
        return '';  // Not used for widget-based auth
    }

    public function setRedirectUrl(string $url): self
    {
        $this->redirectUrl = $url;
        return $this;
    }

    public function setScopes(array $scopes): self
    {
        $this->scopes = $scopes;
        return $this;
    }

    public function setState(string $state): self
    {
        // Widget-based auth doesn't use state, but interface requires it
        return $this;
    }

    public function getUser(): User
    {
        throw new \Exception('Use getUserFromWidgetData for Widget based authentication');
    }

    public function canCreateUser(): bool
    {
        return true;
    }

    public function getScopesForIntent(string $intent): array
    {
        return []; // Telegram widget doesn't use OAuth scopes
    }

    public function isWidgetBased(): bool
    {
        return true;
    }

    public function verifyWidgetData(array $data): bool
    {
        // As per Telegram docs, the hash must be computed from exactly the fields provided by Telegram.
        // Ignore any extra fields we might add client-side (e.g. intent, utm, invite_token, etc.).
        $checkHash = $data['hash'] ?? null;
        if (!$checkHash) {
            throw new InvalidWidgetDataException('Missing Telegram hash');
        }

        // Whitelist allowed keys from Telegram Login Widget
        $allowedKeys = [
            'id',
            'first_name',
            'last_name',
            'username',
            'photo_url',
            'auth_date',
        ];

        // Build data-check-string from allowed fields only
        $filtered = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $data)) {
                $filtered[$key] = $data[$key];
            }
        }

        $dataCheckArr = [];
        foreach ($filtered as $key => $value) {
            $dataCheckArr[] = $key . '=' . $value;
        }

        sort($dataCheckArr);
        $dataCheckString = implode("\n", $dataCheckArr);
        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            throw new InvalidWidgetDataException('Telegram bot token not configured');
        }
        $secretKey = hash('sha256', $botToken, true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);
        if (!hash_equals($hash, $checkHash)) {
            throw new InvalidWidgetDataException('Invalid Telegram signature');
        }

        return true;
    }

    public function getUserFromWidgetData(array $data): array
    {
        return [
            'id' => $data['id'],
            'name' => trim($data['first_name'] . ' ' . ($data['last_name'] ?? '')),
            'email' => $data['email'] ?? null,
            'provider_user_id' => $data['id'],
            'provider' => 'telegram',
            'access_token' => $data['hash'],
            'avatar' => $data['photo_url'] ?? null,
            'scopes' => []
        ];
    }
}
