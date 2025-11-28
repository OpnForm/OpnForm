<?php

namespace App\Integrations\OAuth\Drivers\Contracts;

interface SupportsEmailRestrictions
{
    /**
     * Set email restrictions/hints for the OAuth flow
     */
    public function setEmailRestrictions(array $emails): self;

    /**
     * Check if this driver supports email restrictions
     */
    public function supportsEmailRestrictions(): bool;
}
