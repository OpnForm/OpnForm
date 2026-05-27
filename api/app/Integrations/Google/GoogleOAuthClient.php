<?php

namespace App\Integrations\Google;

use App\Models\OAuthProvider;
use Google\Client as Client;

class GoogleOAuthClient
{
    protected Client $client;

    public function __construct(
        protected OAuthProvider $provider
    ) {
        $this->client = new Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setAccessToken([
            'access_token' => $this->provider->access_token,
            'created' => $this->provider->updated_at->getTimestamp(),
            'expires_in' => 3600,
        ]);
    }

    public function getClient(): Client
    {
        if ($this->client->isAccessTokenExpired()) {
            $this->refreshToken();
        }

        return $this->client;
    }

    public function refreshToken(): static
    {
        if (! $this->provider->refresh_token) {
            return $this;
        }

        $this->client->refreshToken($this->provider->refresh_token);

        $token = $this->client->getAccessToken();
        if (! $token || ! isset($token['access_token'])) {
            return $this;
        }

        $updateData = ['access_token' => $token['access_token']];

        if (isset($token['refresh_token'])) {
            $updateData['refresh_token'] = $token['refresh_token'];
        }

        $this->provider->update($updateData);

        return $this;
    }

    /**
     * Returns a usable access token, refreshing first when the Google client considers it expired.
     *
     * @throws \RuntimeException when no access token is available after refresh
     */
    public function getAccessTokenString(): string
    {
        $client = $this->getClient();
        $token = $client->getAccessToken();

        if (! is_array($token) || empty($token['access_token'])) {
            throw new \RuntimeException('Missing Google access token.');
        }

        if ($client->isAccessTokenExpired()) {
            throw new \RuntimeException('Google access token expired and could not be refreshed.');
        }

        return $token['access_token'];
    }
}
