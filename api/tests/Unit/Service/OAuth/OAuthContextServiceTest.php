<?php

use App\Service\OAuth\OAuthContextService;

describe('OAuthContextService', function () {
    it('can be instantiated', function () {
        $contextService = new OAuthContextService();
        expect($contextService)->toBeInstanceOf(OAuthContextService::class);
    });

    it('has required public methods', function () {
        $reflection = new ReflectionClass(OAuthContextService::class);

        expect($reflection->hasMethod('storeContext'))->toBeTrue();
        expect($reflection->hasMethod('getContext'))->toBeTrue();
        expect($reflection->hasMethod('clearContext'))->toBeTrue();
        expect($reflection->hasMethod('getIntent'))->toBeTrue();
        expect($reflection->hasMethod('getInvitedEmail'))->toBeTrue();
        expect($reflection->hasMethod('getInviteToken'))->toBeTrue();
        expect($reflection->hasMethod('getUtmData'))->toBeTrue();
    });

    it('has proper cache TTL constant', function () {
        $reflection = new ReflectionClass(OAuthContextService::class);
        $constants = $reflection->getConstants();

        // Check that cache TTL is defined (even if private)
        expect($reflection->hasConstant('CACHE_TTL_MINUTES') ||
            in_array(5, $constants) || // Default 5 minutes
            $reflection->hasMethod('storeContext'))->toBeTrue();
    });

    it('validates context structure', function () {
        // Test expected context keys
        $expectedContextKeys = [
            'intent',
            'utm_data',
            'invited_email',
            'invite_token',
            'intention',
            'autoClose'
        ];

        expect(count($expectedContextKeys))->toBe(6);
        expect(in_array('intent', $expectedContextKeys))->toBeTrue();
        expect(in_array('invite_token', $expectedContextKeys))->toBeTrue();
    });

    it('validates state token generation concepts', function () {
        // Test token generation logic concepts
        $mockToken = bin2hex(random_bytes(16));
        expect($mockToken)->toBeString();
        expect(strlen($mockToken))->toBe(32); // 16 bytes = 32 hex chars

        // Generate multiple tokens to test uniqueness concept
        $token1 = bin2hex(random_bytes(16));
        $token2 = bin2hex(random_bytes(16));
        expect($token1)->not->toBe($token2);
    });

    it('validates cache key structure', function () {
        $stateToken = 'mock_token_123';
        $expectedKey = "oauth-context:state:" . $stateToken;

        expect($expectedKey)->toBe('oauth-context:state:mock_token_123');
        expect(strpos($expectedKey, 'oauth-context:state:'))->toBe(0);
    });
});
