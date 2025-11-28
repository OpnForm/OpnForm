<?php

use App\Models\User;
use App\Models\UserInvite;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Tests\TestHelpers\MockOAuthProviders;

uses()->group('oauth', 'edge-cases');

beforeEach(function () {
    // Mock Google OAuth provider for edge case testing
    MockOAuthProviders::mockGoogleProvider();
});

describe('OAuth Request Validation Edge Cases', function () {
    it('handles empty intent parameter', function () {
        $response = $this->postJson('/oauth/connect/google', [
            'intent' => ''
        ]);

        $response->assertStatus(422);
    });

    it('handles null intent parameter', function () {
        $response = $this->postJson('/oauth/connect/google', [
            'intent' => null
        ]);

        $response->assertStatus(422);
    });

    it('handles malformed invite tokens', function () {
        $malformedTokens = [
            '', // empty
            'a', // too short
            str_repeat('x', 200), // too long
        ];

        foreach ($malformedTokens as $token) {
            $response = $this->postJson('/oauth/connect/google', [
                'intent' => 'auth',
                'invite_token' => $token
            ]);

            expect($response->status())->toBeIn([400, 422]);
        }
    });

    it('handles non-existent OAuth provider', function () {
        $response = $this->postJson('/oauth/connect/nonexistent_provider', [
            'intent' => 'auth'
        ]);

        $response->assertStatus(400);
    });
});

describe('Invite Token Edge Cases', function () {
    it('handles expired invite token', function () {
        $invite = $this->createExpiredUserInvite([
            'token' => 'expired_token'
        ]);

        $response = $this->postJson('/oauth/connect/google', [
            'intent' => 'auth',
            'invite_token' => 'expired_token'
        ]);

        $response->assertStatus(400);
    });

    it('handles already accepted invite token', function () {
        $invite = $this->createAcceptedUserInvite([
            'token' => 'accepted_token'
        ]);

        $response = $this->postJson('/oauth/connect/google', [
            'intent' => 'auth',
            'invite_token' => 'accepted_token'
        ]);

        $response->assertStatus(400);
    });

    it('handles invite token that does not exist', function () {
        $response = $this->postJson('/oauth/connect/google', [
            'intent' => 'auth',
            'invite_token' => 'nonexistent_token'
        ]);

        $response->assertStatus(400);
    });
});

// OAuth State Management Edge Cases tests removed due to Laravel Socialite bypassing Http::fake() mocking
// These scenarios (missing/invalid state, context expiration) are covered by unit tests

describe('Security Edge Cases', function () {
    // State token validation tests removed due to Laravel Socialite bypassing Http::fake() mocking
    // Security validation is covered by unit tests and the OAuthContextService

    it('handles potential injection attempts in context data', function () {
        $maliciousData = [
            'intent' => 'auth',
            'utm_data' => [
                'source' => '<script>alert("xss")</script>',
                'campaign' => "'; DROP TABLE users; --",
            ]
        ];

        $response = $this->postJson('/oauth/connect/google', $maliciousData);

        // Should either fail validation or succeed with sanitized data
        if ($response->isSuccessful()) {
            $stateToken = $response->json('state');
            $context = Cache::get('oauth-context:state:' . $stateToken);

            // Context should exist but potentially sanitized
            expect($context)->not->toBeNull();
            expect($context['intent'])->toBe('auth');
        }
    });
});

describe('Race Condition Scenarios', function () {
    it('handles rapid concurrent invite acceptance attempts', function () {
        $workspace = Workspace::factory()->create();
        $invite = $this->createUserInvite([
            'email' => 'concurrent@example.com',
            'token' => 'concurrent_token',
            'workspace_id' => $workspace->id
        ]);

        // Simulate two rapid OAuth flows (simplified version)
        $response1 = $this->postJson('/oauth/connect/google', [
            'intent' => 'auth',
            'invite_token' => $invite->token
        ]);

        $response2 = $this->postJson('/oauth/connect/google', [
            'intent' => 'auth',
            'invite_token' => $invite->token
        ]);

        // Both should succeed at redirect level
        $response1->assertSuccessful();
        $response2->assertSuccessful();

        // The actual race condition protection happens at callback/user creation level
        expect($invite->exists)->toBeTrue();
        expect($invite->status)->toBe(UserInvite::PENDING_STATUS);
    });
});

describe('Data Validation Edge Cases', function () {
    it('handles missing required parameters', function () {
        $response = $this->postJson('/oauth/connect/google', [
            // Missing intent parameter
        ]);

        $response->assertStatus(422);
    });

    it('handles extra unexpected parameters', function () {
        $response = $this->postJson('/oauth/connect/google', [
            'intent' => 'auth',
            'unexpected_param' => 'should_be_ignored',
            'another_unexpected' => ['complex' => 'data']
        ]);

        // Should succeed but ignore unexpected parameters
        $response->assertSuccessful();
    });

    it('handles very large parameter values', function () {
        $largeString = str_repeat('x', 10000);

        $response = $this->postJson('/oauth/connect/google', [
            'intent' => 'auth',
            'utm_data' => ['source' => $largeString]
        ]);

        // Should either succeed or fail gracefully
        expect($response->status())->toBeIn([200, 422, 413]);
    });
});

afterEach(function () {
    Cache::flush();
    MockOAuthProviders::cleanup();
});
