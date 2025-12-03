<?php

namespace App\Enterprise\Oidc\Rules;

use App\Enterprise\Oidc\DomainValidator;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ValidOidcDomain implements Rule
{
    protected DomainValidator $validator;
    protected ?string $userDomain = null;
    protected string $reason = '';

    /**
     * Create a new rule instance.
     */
    public function __construct()
    {
        $this->validator = new DomainValidator();
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        $user = Auth::user();
        if (!$user) {
            $this->reason = 'User not authenticated.';
            return false;
        }

        $domain = (string) $value;

        // Validate domain format first
        if (!$this->isValidDomainFormat($domain)) {
            $this->reason = 'invalid_format';
            return false;
        }

        $this->userDomain = $this->validator->getUserEmailDomain($user);

        // Check if blocked provider
        if ($this->validator->isBlockedProvider($domain)) {
            $this->reason = 'blocked_provider';
            return false;
        }

        // Check if matches user domain
        if (!$this->validator->matchesUserDomain($domain, $user->email)) {
            $this->reason = 'domain_mismatch';
            return false;
        }

        return true;
    }

    /**
     * Validate domain format (structure, label length, valid characters).
     */
    protected function isValidDomainFormat(string $domain): bool
    {
        // Domain must match proper format: alphanumeric labels separated by dots
        // Each label: 1-63 chars, alphanumeric or hyphen, can't start/end with hyphen
        $pattern = '/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/';
        return preg_match($pattern, $domain) === 1;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        if ($this->reason === 'invalid_format') {
            return 'The domain format is invalid.';
        }

        if ($this->reason === 'blocked_provider') {
            return 'Common email providers like gmail.com are not allowed. Please contact support if you need assistance.';
        }

        if ($this->reason === 'domain_mismatch') {
            $domainHint = $this->userDomain ? " ({$this->userDomain})" : '';
            return "The domain must match your email domain{$domainHint}.";
        }

        return 'The domain is invalid.';
    }
}
