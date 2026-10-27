<?php

namespace App\Enterprise\Oidc;

use App\Models\User;
use Pdp\Rules;

class DomainValidator
{
    /**
     * Validate domain for a user.
     *
     * Checks that:
     * 1. Domain is not a blocked email provider
     * 2. Domain matches the user's email domain (exact or root domain)
     *
     * @param  string  $domain
     * @param  User  $user
     * @return bool
     */
    public function validateDomainForUser(string $domain, User $user): bool
    {
        // Check if domain is blocked
        if ($this->isBlockedProvider($domain)) {
            return false;
        }

        // Check if domain matches user's email domain
        return $this->matchesUserDomain($domain, $user->email);
    }

    /**
     * Extract root domain from a domain string.
     *
     * Example: mail.company.com -> company.com
     *
     * @param  string  $domain
     * @return string
     */
    public function extractRootDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        static $rules = null;
        $rules ??= Rules::fromPath(resource_path('data/public_suffix_list.dat'));
        try {
            $resolved = $rules->resolve($domain);
            $registrable = $resolved->registrableDomain()->toString();
            // An unknown suffix or a suffix by itself must never join unrelated domains.
            return $resolved->suffix()->isKnown() && $registrable !== '' ? $registrable : $domain;
        } catch (\Pdp\CannotProcessHost $exception) {
            return $domain;
        }
    }

    /**
     * Check if domain is a blocked email provider.
     *
     * @param  string  $domain
     * @return bool
     */
    public function isBlockedProvider(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        $blockedProviders = config('oidc.blocked_email_providers', []);

        // Check exact match
        if (in_array($domain, $blockedProviders, true)) {
            return true;
        }

        // Check root domain (e.g., mail.gmail.com -> gmail.com)
        $rootDomain = $this->extractRootDomain($domain);
        return in_array($rootDomain, $blockedProviders, true);
    }

    /**
     * Check if domain matches user's email domain (exact or root domain).
     *
     * @param  string  $domain
     * @param  string  $userEmail
     * @return bool
     */
    public function matchesUserDomain(string $domain, string $userEmail): bool
    {
        $userDomain = $this->extractDomainFromEmail($userEmail);
        if (!$userDomain) {
            return false;
        }

        $domain = strtolower(trim($domain));
        $userDomain = strtolower(trim($userDomain));

        // Exact match
        if ($domain === $userDomain) {
            return true;
        }

        // Root domain match
        $domainRoot = $this->extractRootDomain($domain);
        $userDomainRoot = $this->extractRootDomain($userDomain);

        return $domainRoot === $userDomainRoot;
    }

    /**
     * Get email domain from user's email address.
     *
     * @param  User  $user
     * @return string|null
     */
    public function getUserEmailDomain(User $user): ?string
    {
        return $this->extractDomainFromEmail($user->email);
    }

    /**
     * Extract domain from email address.
     *
     * @param  string  $email
     * @return string|null
     */
    protected function extractDomainFromEmail(string $email): ?string
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $parts = explode('@', strtolower(trim($email)));
        return count($parts) === 2 ? $parts[1] : null;
    }
}
