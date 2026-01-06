<?php

use App\Enterprise\Oidc\ExternalUserFactory;
use Tests\TestHelpers;

uses(TestHelpers::class);
uses()->group('oidc', 'feature');

describe('ExternalUserFactory', function () {

    it('creates verified user with normalized email', function () {
        $factory = new ExternalUserFactory();

        $user = $factory->createVerifiedExternalUser(
            name: 'John Doe',
            email: 'JOHN@EXAMPLE.COM',
            provider: 'google',
        );

        expect($user->email)->toBe('john@example.com');
        expect($user->email_verified_at)->not->toBeNull();
        expect($user->name)->toBe('John Doe');
    });

    it('sets provider metadata', function () {
        $factory = new ExternalUserFactory();

        $user = $factory->createVerifiedExternalUser(
            name: 'Jane Doe',
            email: 'jane@example.com',
            provider: 'oidc',
            providerUserId: 'sub-123',
        );

        expect($user->meta['signup_provider'])->toBe('oidc');
        expect($user->meta['signup_provider_user_id'])->toBe('sub-123');
        // registration_ip may be null in unit tests without request context
        expect($user->meta)->toHaveKey('registration_ip');
    });

    it('handles UTM data as array', function () {
        $factory = new ExternalUserFactory();

        $utmData = ['source' => 'google', 'medium' => 'cpc'];
        $user = $factory->createVerifiedExternalUser(
            name: 'Test User',
            email: 'test@example.com',
            provider: 'google',
            utmData: $utmData,
        );

        expect($user->utm_data)->toEqual($utmData);
    });

    it('handles UTM data as JSON string', function () {
        $factory = new ExternalUserFactory();

        $utmData = json_encode(['source' => 'google', 'medium' => 'cpc']);
        $user = $factory->createVerifiedExternalUser(
            name: 'Test User',
            email: 'test@example.com',
            provider: 'google',
            utmData: $utmData,
        );

        expect($user->utm_data)->toEqual(['source' => 'google', 'medium' => 'cpc']);
    });

    it('sets random password when requested', function () {
        $factory = new ExternalUserFactory();

        $user = $factory->createVerifiedExternalUser(
            name: 'Test User',
            email: 'test@example.com',
            provider: 'oidc',
            setRandomPassword: true,
        );

        expect($user->password)->not->toBeNull();
        expect(strlen($user->password))->toBeGreaterThan(50); // Hashed password length
    });

    it('does not set password when not requested', function () {
        $factory = new ExternalUserFactory();

        $user = $factory->createVerifiedExternalUser(
            name: 'Test User',
            email: 'test@example.com',
            provider: 'google',
            setRandomPassword: false,
        );

        expect($user->password)->toBeNull();
    });

    it('merges extra meta fields', function () {
        $factory = new ExternalUserFactory();

        $user = $factory->createVerifiedExternalUser(
            name: 'Test User',
            email: 'test@example.com',
            provider: 'google',
            extraMeta: ['custom_field' => 'custom_value'],
        );

        expect($user->meta['signup_provider'])->toBe('google');
        expect($user->meta['custom_field'])->toBe('custom_value');
    });
});
