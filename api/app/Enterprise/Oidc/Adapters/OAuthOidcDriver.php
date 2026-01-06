<?php

namespace App\Enterprise\Oidc\Adapters;

use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Enterprise\Oidc\Adapters\Socialite\OidcProvider;
use App\Integrations\OAuth\Drivers\Contracts\OAuthDriver;
use Laravel\Socialite\Contracts\User;

class OAuthOidcDriver implements OAuthDriver
{
    private ?string $redirectUrl = null;
    private ?array $scopes = [];
    private ?string $state = null;
    private IdentityConnection $connection;
    private ?string $idToken = null;

    protected OidcProvider $provider;

    public function __construct(IdentityConnection $connection)
    {
        $this->connection = $connection;

        // Create a custom OIDC provider instance
        $redirectUrl = $connection->redirect_url;
        $this->provider = new OidcProvider(
            request(),
            $connection->client_id,
            $connection->client_secret,
            $redirectUrl
        );

        $this->provider->setIssuer($connection->issuer);
    }

    public function getRedirectUrl(): string
    {
        $parameters = [];

        if ($this->state) {
            $parameters['state'] = $this->state;
        }

        $scopes = $this->scopes ?? $this->connection->scopes ?? config('oidc.default_scopes');

        return $this->provider
            ->scopes($scopes)
            ->stateless()
            ->redirectUrl($this->redirectUrl ?? $this->connection->redirect_url)
            ->with($parameters)
            ->redirect()
            ->getTargetUrl();
    }

    public function getUser(): User
    {
        $user = $this->provider
            ->stateless()
            ->redirectUrl($this->redirectUrl ?? $this->connection->redirect_url)
            ->user();

        // Extract ID token from provider's token response
        $this->idToken = $this->provider->getIdToken();

        return $user;
    }

    public function canCreateUser(): bool
    {
        return true;
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
        $this->state = $state;
        return $this;
    }

    public function getScopesForIntent(string $intent): array
    {
        return match ($intent) {
            'auth' => $this->connection->scopes ?? config('oidc.default_scopes'),
            default => $this->connection->scopes ?? config('oidc.default_scopes'),
        };
    }

    public function getIdToken(): ?string
    {
        return $this->idToken;
    }

    /**
     * Get the access token from provider's token response.
     */
    public function getAccessToken(): ?string
    {
        return $this->provider->getAccessToken();
    }

    /**
     * Get the full token response from provider.
     */
    public function getTokenResponse(): array
    {
        return $this->provider->getTokenResponse();
    }
}
