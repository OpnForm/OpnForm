<?php

namespace App\Integrations\OAuth\Drivers\Traits;

/**
 * Classes using this trait should also implement
 * App\Integrations\OAuth\Drivers\Contracts\SupportsEmailRestrictions
 */
trait HasEmailRestrictions
{
    protected array $allowedEmails = [];

    /**
     * Set email restrictions for OAuth flow
     */
    public function setEmailRestrictions(array $emails): self
    {
        $this->allowedEmails = array_map('strtolower', $emails);
        return $this;
    }

    /**
     * Check if this driver supports email restrictions
     */
    public function supportsEmailRestrictions(): bool
    {
        return true;
    }

    /**
     * Get the primary email for login hints
     */
    protected function getPrimaryEmail(): ?string
    {
        return $this->allowedEmails[0] ?? null;
    }

    /**
     * Validate if an email is allowed
     */
    protected function isEmailAllowed(string $email): bool
    {
        return empty($this->allowedEmails) ||
            in_array(strtolower($email), $this->allowedEmails);
    }

    /**
     * Get additional OAuth parameters based on email restrictions
     * Override this method in specific drivers to customize behavior
     */
    protected function getEmailRestrictionParameters(): array
    {
        if ($this->getPrimaryEmail()) {
            return ['login_hint' => $this->getPrimaryEmail()];
        }

        return [];
    }
}
