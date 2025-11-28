<?php

namespace App\Integrations\OAuth\Drivers\Contracts;

use Laravel\Socialite\Contracts\User;

interface OAuthDriver
{
    public function getRedirectUrl(): string;
    public function setRedirectUrl(string $url): self;
    public function setScopes(array $scopes): self;
    public function setState(string $state): self;
    public function getUser(): User;
    public function canCreateUser(): bool;

    /**
     * Get the appropriate scopes for a given intent (auth or integration).
     */
    public function getScopesForIntent(string $intent): array;
}
