<?php

namespace App\Integrations\Google;

use App\Integrations\Google\Sheets\SpreadsheetManager;
use App\Models\Integration\FormIntegration;
use Google\Client as Client;

class Google
{
    protected Client $client;
    protected ?string $token;
    protected ?string $refreshToken;

    public function __construct(
        protected FormIntegration $formIntegration
    ) {
        $this->client = new Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setAccessToken([
            'access_token' => $this->formIntegration->provider->access_token,
            'created' => $this->formIntegration->provider->updated_at->getTimestamp(),
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
        $provider = $this->formIntegration->provider;
        if (!$provider?->refresh_token) {
            return $this;
        }

        $this->client->refreshToken($provider->refresh_token);

        $token = $this->client->getAccessToken();
        if (!$token || !isset($token['access_token'])) {
            return $this;
        }

        $updateData = ['access_token' => $token['access_token']];

        if (isset($token['refresh_token'])) {
            $updateData['refresh_token'] = $token['refresh_token'];
        }

        $provider->update($updateData);

        return $this;
    }

    public function sheets(): SpreadsheetManager
    {
        return new SpreadsheetManager($this, $this->formIntegration);
    }
}
