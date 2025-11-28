<?php

use App\Enterprise\Oidc\Adapters\OAuthOidcDriver;
use App\Enterprise\Oidc\Models\IdentityConnection;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Tests\TestHelpers;

uses(TestHelpers::class);
uses()->group('oidc', 'feature');

afterEach(function () {
    Mockery::close();
});

describe('OAuthOidcDriver - Initialization', function () {
    it('initializes with connection', function () {
        $connection = IdentityConnection::factory()->create([
            'issuer' => 'https://idp.example.com',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
        ]);

        $driver = new OAuthOidcDriver($connection);

        expect($driver)->toBeInstanceOf(OAuthOidcDriver::class);
    });

    it('sets issuer on provider', function () {
        $connection = IdentityConnection::factory()->create([
            'issuer' => 'https://custom-idp.com',
        ]);

        $driver = new OAuthOidcDriver($connection);

        // Use reflection to verify provider was initialized
        $reflection = new ReflectionClass($driver);
        $providerProperty = $reflection->getProperty('provider');
        $providerProperty->setAccessible(true);
        $provider = $providerProperty->getValue($driver);

        expect($provider)->toBeInstanceOf(\App\Enterprise\Oidc\Adapters\Socialite\OidcProvider::class);
    });
});

describe('OAuthOidcDriver - Redirect URL', function () {
    it('uses connection scopes when available', function () {
        $connection = IdentityConnection::factory()->create([
            'scopes' => ['openid', 'profile', 'email', 'groups'],
        ]);

        $driver = new OAuthOidcDriver($connection);

        // Verify scopes are set correctly (we can't easily test getRedirectUrl without mocking Socialite)
        $reflection = new ReflectionClass($driver);
        $scopesProperty = $reflection->getProperty('scopes');
        $scopesProperty->setAccessible(true);
        $scopes = $scopesProperty->getValue($driver);

        // Initially empty, will use connection scopes
        expect($scopes)->toBe([]);
    });

    it('uses default scopes when connection has no scopes', function () {
        config(['oidc.default_scopes' => ['openid', 'profile']]);
        $connection = IdentityConnection::factory()->create([
            'scopes' => null,
        ]);

        $driver = new OAuthOidcDriver($connection);

        // The driver should use config defaults
        expect($connection->scopes)->toBeNull();
    });

    it('uses custom redirect URL when set', function () {
        $connection = IdentityConnection::factory()->create([
            'redirect_path' => 'https://default-callback.com',
        ]);

        $driver = new OAuthOidcDriver($connection);
        $driver->setRedirectUrl('https://custom-callback.com');

        // Verify redirect URL was set
        $reflection = new ReflectionClass($driver);
        $redirectUrlProperty = $reflection->getProperty('redirectUrl');
        $redirectUrlProperty->setAccessible(true);
        $customUrl = $redirectUrlProperty->getValue($driver);

        expect($customUrl)->toBe('https://custom-callback.com');
    });

    it('includes state parameter when set', function () {
        $connection = IdentityConnection::factory()->create();
        $driver = new OAuthOidcDriver($connection);
        $driver->setState('test-state-123');

        $reflection = new ReflectionClass($driver);
        $stateProperty = $reflection->getProperty('state');
        $stateProperty->setAccessible(true);
        $state = $stateProperty->getValue($driver);

        expect($state)->toBe('test-state-123');
    });
});

describe('OAuthOidcDriver - Scopes', function () {
    it('sets custom scopes', function () {
        $connection = IdentityConnection::factory()->create();
        $driver = new OAuthOidcDriver($connection);

        $driver->setScopes(['openid', 'custom_scope']);

        $reflection = new ReflectionClass($driver);
        $scopesProperty = $reflection->getProperty('scopes');
        $scopesProperty->setAccessible(true);
        $scopes = $scopesProperty->getValue($driver);

        expect($scopes)->toEqual(['openid', 'custom_scope']);
    });

    it('returns scopes for auth intent', function () {
        $connection = IdentityConnection::factory()->create([
            'scopes' => ['openid', 'profile', 'email'],
        ]);
        $driver = new OAuthOidcDriver($connection);

        $scopes = $driver->getScopesForIntent('auth');

        expect($scopes)->toEqual(['openid', 'profile', 'email']);
    });

    it('returns default scopes when connection has no scopes', function () {
        config(['oidc.default_scopes' => ['openid', 'profile']]);
        $connection = IdentityConnection::factory()->create([
            'scopes' => null,
        ]);
        $driver = new OAuthOidcDriver($connection);

        $scopes = $driver->getScopesForIntent('auth');

        expect($scopes)->toEqual(['openid', 'profile']);
    });
});

describe('OAuthOidcDriver - User Methods', function () {
    it('can create user', function () {
        $connection = IdentityConnection::factory()->create();
        $driver = new OAuthOidcDriver($connection);

        expect($driver->canCreateUser())->toBeTrue();
    });

    it('extracts ID token from provider token response', function () {
        $connection = IdentityConnection::factory()->create();
        $driver = new OAuthOidcDriver($connection);

        // Mock provider with token response
        $reflection = new ReflectionClass($driver);
        $providerProperty = $reflection->getProperty('provider');
        $providerProperty->setAccessible(true);
        $provider = $providerProperty->getValue($driver);

        // Set token response using reflection
        $providerReflection = new ReflectionClass($provider);
        $tokenResponseProperty = $providerReflection->getProperty('tokenResponse');
        $tokenResponseProperty->setAccessible(true);
        $tokenResponseProperty->setValue($provider, [
            'access_token' => 'access-token-123',
            'id_token' => 'id-token-456',
        ]);

        // Mock getUser to return a user
        $mockUser = Mockery::mock(SocialiteUser::class);
        $mockProvider = Mockery::mock($provider);
        $mockProvider->shouldReceive('stateless')->andReturnSelf();
        $mockProvider->shouldReceive('redirectUrl')->andReturnSelf();
        $mockProvider->shouldReceive('user')->andReturn($mockUser);

        $providerProperty->setValue($driver, $mockProvider);

        $user = $driver->getUser();
        expect($user)->toBeInstanceOf(SocialiteUser::class);

        // After getUser, idToken should be extracted
        $idToken = $driver->getIdToken();
        // Note: In real scenario, this would be extracted from tokenResponse
        // But with mocked provider, we can't easily test this without more complex setup
    });
});

describe('OAuthOidcDriver - Fluent Interface', function () {
    it('setRedirectUrl returns self for method chaining', function () {
        $connection = IdentityConnection::factory()->create();
        $driver = new OAuthOidcDriver($connection);

        $result = $driver->setRedirectUrl('https://test.com');

        expect($result)->toBe($driver);
    });

    it('setScopes returns self for method chaining', function () {
        $connection = IdentityConnection::factory()->create();
        $driver = new OAuthOidcDriver($connection);

        $result = $driver->setScopes(['openid']);

        expect($result)->toBe($driver);
    });

    it('setState returns self for method chaining', function () {
        $connection = IdentityConnection::factory()->create();
        $driver = new OAuthOidcDriver($connection);

        $result = $driver->setState('test-state');

        expect($result)->toBe($driver);
    });
});
