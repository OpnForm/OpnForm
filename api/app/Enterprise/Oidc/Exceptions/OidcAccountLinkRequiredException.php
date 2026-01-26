<?php

namespace App\Enterprise\Oidc\Exceptions;

use Exception;

class OidcAccountLinkRequiredException extends Exception
{
    public function __construct(
        private readonly string $email,
        private readonly string $subject,
        private readonly int $connectionId,
        private readonly array $claims,
        string $message = 'An account with this email already exists. Please link your existing account to continue.'
    ) {
        parent::__construct($message);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getConnectionId(): int
    {
        return $this->connectionId;
    }

    public function getClaims(): array
    {
        return $this->claims;
    }
}
