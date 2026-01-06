<?php

use App\Enterprise\Oidc\DomainValidator;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);
uses()->group('oidc', 'unit');

describe('DomainValidator', function () {
    $validator = new DomainValidator();

    describe('extractRootDomain', function () use ($validator) {
        it('returns domain as-is when it has 2 or fewer parts', function () use ($validator) {
            expect($validator->extractRootDomain('company.com'))->toBe('company.com');
            expect($validator->extractRootDomain('com'))->toBe('com');
        });

        it('extracts root domain from subdomain', function () use ($validator) {
            expect($validator->extractRootDomain('mail.company.com'))->toBe('company.com');
            expect($validator->extractRootDomain('subdomain.example.org'))->toBe('example.org');
        });

        it('handles multiple subdomains', function () use ($validator) {
            expect($validator->extractRootDomain('mail.subdomain.company.com'))->toBe('company.com');
        });

        it('normalizes to lowercase', function () use ($validator) {
            expect($validator->extractRootDomain('MAIL.COMPANY.COM'))->toBe('company.com');
        });

        it('trims whitespace', function () use ($validator) {
            expect($validator->extractRootDomain('  mail.company.com  '))->toBe('company.com');
        });
    });

    describe('isBlockedProvider', function () use ($validator) {
        it('blocks exact matches of blocked providers', function () use ($validator) {
            expect($validator->isBlockedProvider('gmail.com'))->toBeTrue();
            expect($validator->isBlockedProvider('yahoo.com'))->toBeTrue();
            expect($validator->isBlockedProvider('outlook.com'))->toBeTrue();
        });

        it('blocks subdomains of blocked providers', function () use ($validator) {
            expect($validator->isBlockedProvider('mail.gmail.com'))->toBeTrue();
            expect($validator->isBlockedProvider('smtp.yahoo.com'))->toBeTrue();
        });

        it('allows non-blocked domains', function () use ($validator) {
            expect($validator->isBlockedProvider('company.com'))->toBeFalse();
            expect($validator->isBlockedProvider('example.org'))->toBeFalse();
            expect($validator->isBlockedProvider('mail.company.com'))->toBeFalse();
        });

        it('is case-insensitive', function () use ($validator) {
            expect($validator->isBlockedProvider('GMAIL.COM'))->toBeTrue();
            expect($validator->isBlockedProvider('Gmail.Com'))->toBeTrue();
        });

        it('blocks all configured providers', function () use ($validator) {
            $blockedProviders = [
                'gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com',
                'msn.com', 'yahoo.com', 'yahoo.co.uk', 'ymail.com', 'rocketmail.com',
                'icloud.com', 'me.com', 'mac.com', 'aol.com', 'protonmail.com',
                'proton.me', 'mail.com', 'gmx.com', 'gmx.de', 'gmx.net', 'zoho.com',
            ];

            foreach ($blockedProviders as $provider) {
                expect($validator->isBlockedProvider($provider))->toBeTrue("Failed to block: {$provider}");
            }
        });
    });

    describe('matchesUserDomain', function () use ($validator) {
        it('matches exact domain', function () use ($validator) {
            expect($validator->matchesUserDomain('company.com', 'user@company.com'))->toBeTrue();
            expect($validator->matchesUserDomain('example.org', 'test@example.org'))->toBeTrue();
        });

        it('matches root domain when user has subdomain email', function () use ($validator) {
            expect($validator->matchesUserDomain('company.com', 'user@mail.company.com'))->toBeTrue();
            expect($validator->matchesUserDomain('example.org', 'test@subdomain.example.org'))->toBeTrue();
        });

        it('matches subdomain when user has root domain email', function () use ($validator) {
            expect($validator->matchesUserDomain('mail.company.com', 'user@company.com'))->toBeTrue();
            expect($validator->matchesUserDomain('subdomain.example.org', 'test@example.org'))->toBeTrue();
        });

        it('does not match different domains', function () use ($validator) {
            expect($validator->matchesUserDomain('other.com', 'user@company.com'))->toBeFalse();
            expect($validator->matchesUserDomain('company.com', 'user@other.com'))->toBeFalse();
        });

        it('is case-insensitive', function () use ($validator) {
            expect($validator->matchesUserDomain('COMPANY.COM', 'user@company.com'))->toBeTrue();
            expect($validator->matchesUserDomain('company.com', 'user@COMPANY.COM'))->toBeTrue();
        });

        it('returns false for invalid email', function () use ($validator) {
            expect($validator->matchesUserDomain('company.com', 'invalid-email'))->toBeFalse();
            expect($validator->matchesUserDomain('company.com', ''))->toBeFalse();
        });

        it('handles complex subdomain scenarios', function () use ($validator) {
            // User has mail.company.com, domain is company.com
            expect($validator->matchesUserDomain('company.com', 'user@mail.company.com'))->toBeTrue();

            // User has company.com, domain is mail.company.com
            expect($validator->matchesUserDomain('mail.company.com', 'user@company.com'))->toBeTrue();

            // Both have same subdomain
            expect($validator->matchesUserDomain('mail.company.com', 'user@mail.company.com'))->toBeTrue();
        });
    });

    describe('getUserEmailDomain', function () use ($validator) {
        it('extracts domain from user email', function () use ($validator) {
            $user = new User(['email' => 'user@company.com']);
            expect($validator->getUserEmailDomain($user))->toBe('company.com');
        });

        it('handles subdomain emails', function () use ($validator) {
            $user = new User(['email' => 'user@mail.company.com']);
            expect($validator->getUserEmailDomain($user))->toBe('mail.company.com');
        });

        it('returns null for invalid email', function () use ($validator) {
            $user = new User(['email' => 'invalid-email']);
            expect($validator->getUserEmailDomain($user))->toBeNull();
        });

        it('returns null for empty email', function () use ($validator) {
            $user = new User(['email' => '']);
            expect($validator->getUserEmailDomain($user))->toBeNull();
        });
    });

    describe('validateDomainForUser', function () use ($validator) {
        it('validates domain matches user email domain', function () use ($validator) {
            $user = new User(['email' => 'user@company.com']);
            expect($validator->validateDomainForUser('company.com', $user))->toBeTrue();
        });

        it('rejects blocked providers', function () use ($validator) {
            $user = new User(['email' => 'user@company.com']);
            expect($validator->validateDomainForUser('gmail.com', $user))->toBeFalse();
        });

        it('rejects domains that do not match user email', function () use ($validator) {
            $user = new User(['email' => 'user@company.com']);
            expect($validator->validateDomainForUser('other.com', $user))->toBeFalse();
        });

        it('allows root domain match', function () use ($validator) {
            $user = new User(['email' => 'user@mail.company.com']);
            expect($validator->validateDomainForUser('company.com', $user))->toBeTrue();
        });

        it('allows subdomain match', function () use ($validator) {
            $user = new User(['email' => 'user@company.com']);
            expect($validator->validateDomainForUser('mail.company.com', $user))->toBeTrue();
        });
    });
});
