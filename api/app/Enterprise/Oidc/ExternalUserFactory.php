<?php

namespace App\Enterprise\Oidc;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ExternalUserFactory
 *
 * Shared factory for creating users coming from external identity providers
 * (OAuth providers, OIDC connections, etc.).
 *
 * Responsibilities:
 * - Normalize email casing
 * - Apply UTM data when available
 * - Populate common meta fields (signup provider, provider user id, registration IP)
 * - Optionally set a random password for passwordless accounts
 */
class ExternalUserFactory
{
    /**
     * Create a verified external user.
     *
     * @param  string       $name
     * @param  string       $email
     * @param  string       $provider         Logical provider key (e.g. 'google', 'stripe', 'oidc')
     * @param  string|null  $providerUserId   Provider-side user identifier (sub, provider_user_id, etc.)
     * @param  mixed|null   $utmData          UTM payload (array or JSON string)
     * @param  array        $extraMeta        Additional meta fields to merge
     * @param  bool         $setRandomPassword Whether to set a random password (for SSO-only accounts)
     */
    public function createVerifiedExternalUser(
        string $name,
        string $email,
        string $provider,
        ?string $providerUserId = null,
        mixed $utmData = null,
        array $extraMeta = [],
        bool $setRandomPassword = false,
    ): User {
        $normalizedEmail = strtolower($email);

        $meta = array_merge([
            'signup_provider' => $provider,
            'signup_provider_user_id' => $providerUserId,
            'registration_ip' => app()->bound('request') ? request()->ip() : null,
        ], $extraMeta);

        $attributes = [
            'name' => $name,
            'email' => $normalizedEmail,
            'email_verified_at' => now(),
            'utm_data' => is_string($utmData) ? json_decode($utmData, true) : $utmData,
            'meta' => $meta,
        ];

        if ($setRandomPassword) {
            // Prevent password-based login for SSO accounts
            $attributes['password'] = Hash::make(Str::random(64));
        }

        // Use forceFill to bypass fillable restrictions for email_verified_at
        $user = new User();
        $user->forceFill($attributes);
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
