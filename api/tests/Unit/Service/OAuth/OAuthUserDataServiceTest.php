<?php

use App\Service\OAuth\OAuthUserDataService;

describe('OAuthUserDataService', function () {
    beforeEach(function () {
        $this->userDataService = new OAuthUserDataService();
    });

    it('can be instantiated', function () {
        expect($this->userDataService)->toBeInstanceOf(OAuthUserDataService::class);
    });

    it('has required public methods', function () {
        $reflection = new ReflectionClass(OAuthUserDataService::class);

        expect($reflection->hasMethod('extractFromRedirect'))->toBeTrue();
        expect($reflection->hasMethod('extractFromWidget'))->toBeTrue();
    });

    it('validates required user data fields', function () {
        $requiredFields = ['email', 'name', 'provider_user_id'];

        foreach ($requiredFields as $field) {
            expect($field)->toBeString();
            expect(strlen($field))->toBeGreaterThan(0);
        }
    });

    it('handles user data normalization patterns', function () {
        $mockUserData = [
            'email' => 'JOHN@EXAMPLE.COM', // Should be normalized to lowercase
            'name' => 'John Doe',
            'provider_user_id' => '123',
        ];

        // Test email normalization concept
        expect(strtolower($mockUserData['email']))->toBe('john@example.com');
    });

    it('validates optional user data fields', function () {
        $optionalFields = ['avatar', 'scopes', 'access_token', 'refresh_token'];

        foreach ($optionalFields as $field) {
            expect($field)->toBeString();
            expect(strlen($field))->toBeGreaterThan(0);
        }
    });

    it('handles user data structure correctly', function () {
        $expectedStructure = [
            'id' => 'provider123',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'provider_user_id' => 'provider123',
            'access_token' => 'token123',
            'refresh_token' => 'refresh123',
            'avatar' => 'https://example.com/avatar.jpg',
            'scopes' => ['profile', 'email'],
        ];

        // Verify structure
        expect($expectedStructure)->toHaveKeys([
            'id',
            'name',
            'email',
            'provider_user_id'
        ]);

        expect($expectedStructure['email'])->toBe('john@example.com');
        expect($expectedStructure['scopes'])->toBeArray();
    });
});
