<?php

use App\Enterprise\Oidc\Rules\ValidOidcDomain;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);
uses()->group('oidc', 'unit');

describe('ValidOidcDomain Rule', function () {
    it('passes when domain matches user email domain', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $validator = Validator::make(
            ['domain' => 'company.com'],
            ['domain' => [new ValidOidcDomain()]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails when domain does not match user email domain', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $validator = Validator::make(
            ['domain' => 'other.com'],
            ['domain' => [new ValidOidcDomain()]]
        );

        expect($validator->passes())->toBeFalse();
        expect($validator->errors()->first('domain'))->toContain('must match your email domain');
    });

    it('fails when domain is a blocked provider', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $validator = Validator::make(
            ['domain' => 'gmail.com'],
            ['domain' => [new ValidOidcDomain()]]
        );

        expect($validator->passes())->toBeFalse();
        expect($validator->errors()->first('domain'))->toContain('Common email providers');
    });

    it('fails when user is not authenticated', function () {
        // Don't authenticate any user
        $validator = Validator::make(
            ['domain' => 'company.com'],
            ['domain' => [new ValidOidcDomain()]]
        );

        expect($validator->passes())->toBeFalse();
    });

    it('allows root domain match when user has subdomain email', function () {
        $user = User::factory()->create(['email' => 'user@mail.company.com']);
        $this->actingAs($user);

        $validator = Validator::make(
            ['domain' => 'company.com'],
            ['domain' => [new ValidOidcDomain()]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('allows subdomain match when user has root domain email', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $validator = Validator::make(
            ['domain' => 'mail.company.com'],
            ['domain' => [new ValidOidcDomain()]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('includes user domain in error message when available', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $validator = Validator::make(
            ['domain' => 'other.com'],
            ['domain' => [new ValidOidcDomain()]]
        );

        expect($validator->passes())->toBeFalse();
        $errorMessage = $validator->errors()->first('domain');
        expect($errorMessage)->toContain('company.com');
    });

    it('rejects invalid domain format', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $invalidDomains = [
            '-invalid.com',      // Starts with hyphen
            'invalid-.com',      // Ends with hyphen
            'invalid..com',      // Double dots
            '.invalid.com',      // Starts with dot
            'invalid.com.',      // Ends with dot
            'a' . str_repeat('b', 64) . '.com', // Label too long (>63 chars)
            'invalid com',       // Space
        ];

        foreach ($invalidDomains as $invalidDomain) {
            $validator = Validator::make(
                ['domain' => $invalidDomain],
                ['domain' => ['required', new ValidOidcDomain()]]
            );

            expect($validator->passes())->toBeFalse("Domain '{$invalidDomain}' should be rejected");
            expect($validator->errors()->first('domain'))->toContain('format');
        }
    });
});
