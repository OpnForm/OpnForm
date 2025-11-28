<?php

namespace App\Service\OAuth;

use App\Models\UserInvite;
use App\Integrations\OAuth\Drivers\Contracts\OAuthDriver;
use App\Integrations\OAuth\Drivers\Contracts\SupportsEmailRestrictions;

/**
 * OAuthInviteService
 *
 * Manages OAuth authentication with workspace invitations.
 * Responsibilities:
 * - Validating invitation tokens
 * - Checking token expiration
 * - Extracting invited email addresses
 * - Configuring OAuth drivers with email restrictions
 * - Enforcing email address restrictions during authentication
 *
 * Ensures that users registering via OAuth invitations authenticate
 * with the email address that was invited, preventing unauthorized access.
 */
class OAuthInviteService
{
    /**
     * Validate invite token and return the invite
     */
    public function validateInviteToken(string $inviteToken): UserInvite
    {
        $userInvite = UserInvite::where('token', $inviteToken)
            ->where('status', UserInvite::PENDING_STATUS)
            ->first();

        if (!$userInvite) {
            abort(400, 'Invalid invite token');
        }

        if ($userInvite->hasExpired()) {
            abort(400, 'Invite token has expired');
        }

        return $userInvite;
    }

    /**
     * Get invited email from token
     */
    public function getInvitedEmail(string $inviteToken): string
    {
        return $this->validateInviteToken($inviteToken)->email;
    }

    /**
     * Configure driver with email restrictions (capability-based approach)
     */
    public function configureDriverEmailRestrictions(
        OAuthDriver $driver,
        ?string $invitedEmail = null
    ): void {
        if (!$invitedEmail || !($driver instanceof SupportsEmailRestrictions)) {
            return;
        }

        $driver->setEmailRestrictions([$invitedEmail]);
    }

    /**
     * Validate email matches invited email for OAuth authentication
     */
    public function validateEmailRestrictions(string $email, ?string $invitedEmail = null): void
    {
        if ($invitedEmail && strtolower($email) !== strtolower($invitedEmail)) {
            abort(400, 'You must login with the invited email address: ' . $invitedEmail);
        }
    }
}
