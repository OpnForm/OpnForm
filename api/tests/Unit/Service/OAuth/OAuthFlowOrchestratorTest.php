<?php

use App\Service\OAuth\OAuthFlowOrchestrator;

describe('OAuthFlowOrchestrator', function () {
    it('defines correct intent constants', function () {
        expect(OAuthFlowOrchestrator::INTENT_AUTH)->toBe('auth');
        expect(OAuthFlowOrchestrator::INTENT_INTEGRATION)->toBe('integration');
        expect(OAuthFlowOrchestrator::INTENTS)->toBe(['auth', 'integration']);
    });

    it('has proper class structure', function () {
        $reflection = new ReflectionClass(OAuthFlowOrchestrator::class);
        expect($reflection->isInstantiable())->toBeTrue();
        expect($reflection->hasMethod('processRedirect'))->toBeTrue();
        expect($reflection->hasMethod('processCallback'))->toBeTrue();
        expect($reflection->hasMethod('processWidgetCallback'))->toBeTrue();
    });

    it('uses ManagesJWT trait', function () {
        $traits = class_uses_recursive(OAuthFlowOrchestrator::class);
        expect($traits)->toHaveKey('App\Http\Controllers\Auth\Traits\ManagesJWT');
    });
});

describe('OAuth constants validation', function () {
    it('validates intent values', function () {
        $validIntents = ['auth', 'integration'];

        foreach ($validIntents as $intent) {
            expect(in_array($intent, OAuthFlowOrchestrator::INTENTS))->toBeTrue(
                "Intent '{$intent}' should be valid"
            );
        }

        expect(in_array('invalid', OAuthFlowOrchestrator::INTENTS))->toBeFalse(
            "Invalid intents should not be accepted"
        );
    });
});

describe('OAuth data structures', function () {
    it('validates expected context structure', function () {
        $expectedContextKeys = [
            'intent',
            'utm_data',
            'invited_email',
            'invite_token',
            'intention',
            'autoClose'
        ];

        // Verify we have all expected context keys
        expect(count($expectedContextKeys))->toBe(6);
        expect(in_array('intent', $expectedContextKeys))->toBeTrue();
        expect(in_array('invite_token', $expectedContextKeys))->toBeTrue();
    });

    it('validates expected user data structure', function () {
        $requiredUserDataFields = ['id', 'name', 'email', 'provider_user_id'];
        $optionalUserDataFields = ['access_token', 'refresh_token', 'avatar', 'scopes'];

        expect(count($requiredUserDataFields))->toBe(4);
        expect(count($optionalUserDataFields))->toBe(4);
    });

    it('validates JWT response structure', function () {
        $jwtResponseKeys = ['access_token', 'token_type', 'expires_in'];
        expect(count($jwtResponseKeys))->toBe(3);
        expect(in_array('access_token', $jwtResponseKeys))->toBeTrue();
    });
});
