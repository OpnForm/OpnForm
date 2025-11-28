<?php

namespace Tests\TestHelpers;

use Illuminate\Support\Facades\Http;

/**
 * Helper class for mocking OAuth provider APIs in tests
 *
 * Note: Due to Laravel Socialite using its own Guzzle client that bypasses
 * Laravel's Http::fake(), comprehensive OAuth callback flow testing is not
 * feasible with HTTP mocking. These mocks are used for basic request validation.
 *
 * OAuth business logic is thoroughly tested in unit tests, and full integration
 * testing should be done in staging/manual environments.
 */
class MockOAuthProviders
{
    /**
     * Basic Google OAuth provider mocking for redirect validation
     */
    public static function mockGoogleProvider(): void
    {
        // Minimal mocking for OAuth redirect endpoints
        Http::fake([
            'https://accounts.google.com/*' => Http::response(['redirect' => 'mocked']),
            'https://*.googleapis.com/*' => Http::response(['mocked' => true]),
        ]);
    }

    /**
     * Basic Stripe OAuth provider mocking
     */
    public static function mockStripeProvider(): void
    {
        Http::fake([
            'https://connect.stripe.com/*' => Http::response(['mocked' => true]),
            'https://api.stripe.com/*' => Http::response(['mocked' => true]),
        ]);
    }

    /**
     * Mock all OAuth providers
     */
    public static function mockAllProviders(): void
    {
        self::mockGoogleProvider();
        self::mockStripeProvider();
    }

    /**
     * Clean up all mocked HTTP responses
     */
    public static function cleanup(): void
    {
        Http::fake(); // Reset all fakes
    }
}
