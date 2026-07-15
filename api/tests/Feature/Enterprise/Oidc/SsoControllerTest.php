<?php

use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Tests\TestHelpers;

require_once __DIR__ . '/../../../TestHelpers/OidcTestHelpers.php';

uses(TestHelpers::class);
uses()->group('oidc', 'feature');

if (!function_exists('cacheOidcState')) {
    function cacheOidcState(IdentityConnection $connection, string $state, string $verifier = 'valid-state-verifier', int $ttlSeconds = 600): string
    {
        Cache::put("oidc_login_state:{$state}", [
            'connection_id' => $connection->id,
            'verifier_hash' => hash('sha256', $verifier),
        ], $ttlSeconds);

        return $verifier;
    }
}

if (!function_exists('oidcVerifierHeaders')) {
    function oidcVerifierHeaders(string $verifier): array
    {
        return [
            'Accept' => 'application/json',
            'X-OIDC-State-Verifier' => $verifier,
        ];
    }
}

afterEach(function () {
    Mockery::close();
    Cache::flush();
});

describe('SsoController - Redirect', function () {
    it('returns redirect URL for enabled connection', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'test-sso',
            'enabled' => true,
        ]);

        $capturedState = null;

        // Mock the driver to return a redirect URL
        $mockDriver = Mockery::mock(\App\Enterprise\Oidc\Adapters\OAuthOidcDriver::class);
        $mockDriver->shouldReceive('setState')
            ->once()
            ->with(Mockery::on(function ($state) use (&$capturedState) {
                $capturedState = $state;

                return is_string($state) && strlen($state) === 32;
            }))
            ->andReturnSelf();
        $mockDriver->shouldReceive('getRedirectUrl')->andReturn('https://idp.example.com/authorize');

        $mockConnectionManager = Mockery::mock(\App\Enterprise\Oidc\ConnectionManager::class);
        $mockConnectionManager->shouldReceive('getConnectionBySlug')
            ->with('test-sso')
            ->andReturn($connection);
        $mockConnectionManager->shouldReceive('buildDriver')
            ->with($connection)
            ->andReturn($mockDriver);

        $this->app->instance(\App\Enterprise\Oidc\ConnectionManager::class, $mockConnectionManager);

        $response = $this->postJson("/auth/test-sso/redirect");

        $response->assertSuccessful();
        $response->assertJson([
            'redirect_url' => 'https://idp.example.com/authorize',
        ]);

        $stateVerifier = $response->json('state_verifier');
        $storedState = Cache::get("oidc_login_state:{$capturedState}");

        expect($response->json('state'))->toBe($capturedState)
            ->and($stateVerifier)->toBeString()
            ->and(strlen($stateVerifier))->toBe(64)
            ->and($storedState['connection_id'])->toBe($connection->id)
            ->and(hash_equals($storedState['verifier_hash'], hash('sha256', $stateVerifier)))->toBeTrue();
    });

    it('does not add state when explicitly disabled on the connection', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'state-disabled',
            'enabled' => true,
            'options' => [
                'require_state' => false,
            ],
        ]);

        $mockDriver = Mockery::mock(\App\Enterprise\Oidc\Adapters\OAuthOidcDriver::class);
        $mockDriver->shouldNotReceive('setState');
        $mockDriver->shouldReceive('getRedirectUrl')->andReturn('https://idp.example.com/authorize');

        $mockConnectionManager = Mockery::mock(\App\Enterprise\Oidc\ConnectionManager::class);
        $mockConnectionManager->shouldReceive('getConnectionBySlug')
            ->with('state-disabled')
            ->andReturn($connection);
        $mockConnectionManager->shouldReceive('buildDriver')
            ->with($connection)
            ->andReturn($mockDriver);

        $this->app->instance(\App\Enterprise\Oidc\ConnectionManager::class, $mockConnectionManager);

        $response = $this->postJson("/auth/{$connection->slug}/redirect");

        $response->assertSuccessful();
        $response->assertJson([
            'redirect_url' => 'https://idp.example.com/authorize',
        ]);
        expect($response->json('state'))->toBeNull()
            ->and($response->json('state_verifier'))->toBeNull();
    });

    it('returns 404 for non-existent connection', function () {
        $mockConnectionManager = Mockery::mock(\App\Enterprise\Oidc\ConnectionManager::class);
        $mockConnectionManager->shouldReceive('getConnectionBySlug')
            ->with('non-existent')
            ->andReturn(null);

        $this->app->instance(\App\Enterprise\Oidc\ConnectionManager::class, $mockConnectionManager);

        $response = $this->postJson("/auth/non-existent/redirect");

        $response->assertNotFound();
        $response->assertJson([
            'error' => 'OIDC connection not found or disabled',
        ]);
    });

    it('returns 404 for disabled connection', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'disabled-sso',
            'enabled' => false,
        ]);

        $mockConnectionManager = Mockery::mock(\App\Enterprise\Oidc\ConnectionManager::class);
        $mockConnectionManager->shouldReceive('getConnectionBySlug')
            ->with('disabled-sso')
            ->andReturn($connection);

        $this->app->instance(\App\Enterprise\Oidc\ConnectionManager::class, $mockConnectionManager);

        $response = $this->postJson("/auth/disabled-sso/redirect");

        $response->assertNotFound();
        $response->assertJson([
            'error' => 'OIDC connection not found or disabled',
        ]);
    });

    it('requires HTTPS in production', function () {
        $originalEnv = config('app.env');
        config(['app.env' => 'production']);

        $connection = IdentityConnection::factory()->create([
            'slug' => 'test-sso',
            'enabled' => true,
        ]);

        // Mock driver to avoid initialization issues
        $mockDriver = Mockery::mock(\App\Enterprise\Oidc\Adapters\OAuthOidcDriver::class);
        $mockDriver->shouldReceive('getRedirectUrl')->andReturn('https://idp.example.com/authorize');

        $mockConnectionManager = Mockery::mock(\App\Enterprise\Oidc\ConnectionManager::class);
        $mockConnectionManager->shouldReceive('getConnectionBySlug')
            ->with('test-sso')
            ->andReturn($connection);
        $mockConnectionManager->shouldReceive('buildDriver')
            ->with($connection)
            ->andReturn($mockDriver);

        $this->app->instance(\App\Enterprise\Oidc\ConnectionManager::class, $mockConnectionManager);

        // Force HTTP scheme and simulate non-HTTPS request
        URL::forceScheme('http');
        $response = $this->postJson("/auth/test-sso/redirect", [
            'HTTP_X_FORWARDED_PROTO' => 'http',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'HTTPS is required for OIDC authentication',
        ]);

        // Restore original env and scheme
        config(['app.env' => $originalEnv]);
        URL::forceScheme('https');
    });
});

describe('SsoController - Rate limiting', function () {
    it('limits OIDC sign-in initiation per connection and returns the retry delay', function () {
        config(['oidc.rate_limit_per_minute' => 2]);
        $this->withMiddleware(ThrottleRequests::class);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20']);

        $this->postJson('/auth/company-sso/redirect')->assertNotFound();
        $this->postJson('/auth/company-sso/redirect')->assertNotFound();

        $response = $this->postJson('/auth/company-sso/redirect');

        $retryAfter = (int) $response->headers->get('Retry-After');
        $response->assertStatus(429)
            ->assertJson([
                'error' => 'oidc_rate_limited',
                'retry_after' => $retryAfter,
            ]);

        expect($retryAfter)->toBeGreaterThan(0)
            ->and($response->json('message'))->toContain("{$retryAfter} seconds");

        $this->postJson('/auth/another-company-sso/redirect')->assertNotFound();
    });

    it('does not put callbacks in the OIDC initiation bucket', function () {
        config(['oidc.rate_limit_per_minute' => 1]);
        $this->withMiddleware(ThrottleRequests::class);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.21']);

        $this->getJson('/auth/company-sso/callback')->assertNotFound();
        $this->getJson('/auth/company-sso/callback')->assertNotFound();

        $this->postJson('/auth/company-sso/redirect')->assertNotFound();
        $this->postJson('/auth/company-sso/redirect')->assertStatus(429);
    });

    it('uses the forwarded client IP when the reverse proxy is explicitly trusted', function () {
        config([
            'oidc.rate_limit_per_minute' => 1,
            'trustedproxy.proxies' => '192.0.2.30',
        ]);
        $this->withMiddleware(ThrottleRequests::class);

        $this->withServerVariables([
            'REMOTE_ADDR' => '192.0.2.30',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.30',
        ]);
        $this->postJson('/auth/company-sso/redirect')->assertNotFound();

        $this->withServerVariables([
            'REMOTE_ADDR' => '192.0.2.30',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.31',
        ]);
        $this->postJson('/auth/company-sso/redirect')->assertNotFound();

        $this->withServerVariables([
            'REMOTE_ADDR' => '192.0.2.30',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.30',
        ]);
        $this->postJson('/auth/company-sso/redirect')->assertStatus(429);
    });

    it('does not trust a forwarded client IP from an untrusted sender', function () {
        config([
            'oidc.rate_limit_per_minute' => 1,
            'trustedproxy.proxies' => '192.0.2.40',
        ]);
        $this->withMiddleware(ThrottleRequests::class);

        $this->withServerVariables([
            'REMOTE_ADDR' => '192.0.2.41',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.40',
        ]);
        $this->postJson('/auth/company-sso/redirect')->assertNotFound();

        $this->withServerVariables([
            'REMOTE_ADDR' => '192.0.2.41',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.41',
        ]);
        $this->postJson('/auth/company-sso/redirect')->assertStatus(429);
    });
});

describe('SsoController - Get Options For Email', function () {
    it('returns redirect action when connection exists for domain', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'company-sso',
            'domain' => 'company.com',
            'enabled' => true,
        ]);

        $response = $this->postJson('/auth/oidc/options', [
            'email' => 'user@company.com',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'action' => 'redirect',
            'slug' => 'company-sso',
        ]);
    });

    it('returns fallback action when no connection exists for domain', function () {
        IdentityConnection::factory()->create([
            'domain' => 'other.com',
            'enabled' => true,
        ]);

        $response = $this->postJson('/auth/oidc/options', [
            'email' => 'user@company.com',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'action' => 'fallback',
        ]);
    });

    it('returns blocked action when force login is enabled and no connection', function () {
        config(['oidc.force_login' => true]);

        $response = $this->postJson('/auth/oidc/options', [
            'email' => 'user@company.com',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'action' => 'blocked',
        ]);
    });

    it('returns fallback action for invalid email', function () {
        $response = $this->postJson('/auth/oidc/options', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    });

    it('only matches enabled connections', function () {
        IdentityConnection::factory()->create([
            'slug' => 'disabled-sso',
            'domain' => 'company.com',
            'enabled' => false,
        ]);

        $response = $this->postJson('/auth/oidc/options', [
            'email' => 'user@company.com',
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'action' => 'fallback',
        ]);
    });

    it('extracts domain correctly from email', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'test-sso',
            'domain' => 'example.com',
            'enabled' => true,
        ]);

        $response = $this->postJson('/auth/oidc/options', [
            'email' => 'USER@EXAMPLE.COM', // Test case normalization
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'action' => 'redirect',
        ]);
    });
});

describe('SsoController - Callback', function () {
    it('returns JSON response with token and user for new user', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'test-sso',
            'enabled' => true,
        ]);

        $socialiteUser = createMockSocialiteUser(
            email: 'newuser@example.com',
            name: 'New User'
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-123');
        $idToken = createMockIdToken($idTokenClaims);

        // Mock driver
        $mockDriver = Mockery::mock(\App\Enterprise\Oidc\Adapters\OAuthOidcDriver::class);
        $mockDriver->shouldReceive('setRedirectUrl')->andReturnSelf();
        $mockDriver->shouldReceive('getUser')->andReturn($socialiteUser);
        $mockDriver->shouldReceive('getIdToken')->andReturn($idToken);

        $mockConnectionManager = Mockery::mock(\App\Enterprise\Oidc\ConnectionManager::class);
        $mockConnectionManager->shouldReceive('getConnectionBySlug')
            ->with('test-sso')
            ->andReturn($connection);
        $mockConnectionManager->shouldReceive('buildDriver')
            ->with($connection)
            ->andReturn($mockDriver);

        // Mock IdTokenVerifier to skip signature verification in tests
        $mockIdTokenVerifier = Mockery::mock(\App\Enterprise\Oidc\IdTokenVerifier::class);
        $mockIdTokenVerifier->shouldReceive('verifySignature')
            ->with($connection, $idToken)
            ->andReturnNull();

        $this->app->instance(\App\Enterprise\Oidc\ConnectionManager::class, $mockConnectionManager);
        $this->app->instance(\App\Enterprise\Oidc\IdTokenVerifier::class, $mockIdTokenVerifier);

        $state = 'new-user-state-token';
        $stateVerifier = cacheOidcState($connection, $state);

        $response = $this->getJson("/auth/test-sso/callback?state={$state}", [
            ...oidcVerifierHeaders($stateVerifier),
        ]);

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'token',
            'token_type',
            'expires_in',
            'user' => [
                'id',
                'email',
                'name',
            ],
            'new_user',
            'redirect_url',
        ]);
        expect($response->json('new_user'))->toBeTrue();
    });

    it('rejects callback when state is missing', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'state-required',
            'enabled' => true,
        ]);

        $response = $this->getJson("/auth/{$connection->slug}/callback", [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(400);
        expect($response->json('message'))->toContain('Missing or invalid state');
    });

    it('rejects callback when state verifier is missing', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'state-required',
            'enabled' => true,
        ]);

        $state = 'missing-verifier-state';
        cacheOidcState($connection, $state);

        $response = $this->getJson("/auth/{$connection->slug}/callback?state={$state}", [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(400);
        expect($response->json('message'))->toContain('Invalid state');
    });

    it('rejects callback when state was not issued by the OIDC login flow', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'state-required',
            'enabled' => true,
        ]);

        $state = 'attacker-session-state';
        Cache::put("oidc_state_{$state}", true, 600);

        $response = $this->getJson("/auth/{$connection->slug}/callback?state={$state}", [
            ...oidcVerifierHeaders('attacker-verifier'),
        ]);

        $response->assertStatus(400);
        expect($response->json('message'))->toContain('Invalid state');
    });

    it('rejects callback when state verifier does not match', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'state-required',
            'enabled' => true,
        ]);

        $state = 'wrong-verifier-state';
        cacheOidcState($connection, $state, 'correct-verifier');

        $response = $this->getJson("/auth/{$connection->slug}/callback?state={$state}", [
            ...oidcVerifierHeaders('wrong-verifier'),
        ]);

        $response->assertStatus(400);
        expect($response->json('message'))->toContain('Invalid state');
        expect(Cache::get("oidc_login_state:{$state}"))->not->toBeNull();
    });

    it('rejects callback when state belongs to a different connection', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'state-required',
            'enabled' => true,
        ]);
        $otherConnection = IdentityConnection::factory()->create([
            'slug' => 'other-state-owner',
            'enabled' => true,
        ]);

        $state = 'other-connection-state';
        $stateVerifier = cacheOidcState($otherConnection, $state);

        $response = $this->getJson("/auth/{$connection->slug}/callback?state={$state}", [
            ...oidcVerifierHeaders($stateVerifier),
        ]);

        $response->assertStatus(400);
        expect($response->json('message'))->toContain('Invalid state');
    });

    it('rejects callback when state has expired in the current session', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'state-required',
            'enabled' => true,
        ]);

        $state = 'expired-state';
        $stateVerifier = cacheOidcState($connection, $state, 'expired-verifier', -1);

        $response = $this->getJson("/auth/{$connection->slug}/callback?state={$state}", [
            ...oidcVerifierHeaders($stateVerifier),
        ]);

        $response->assertStatus(400);
        expect($response->json('message'))->toContain('Invalid state');
    });

    it('accepts callback without state when explicitly disabled on the connection', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'state-disabled',
            'enabled' => true,
            'options' => [
                'require_state' => false,
            ],
        ]);

        $socialiteUser = createMockSocialiteUser(
            email: 'nostate@example.com',
            name: 'No State User'
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-no-state');
        $idToken = createMockIdToken($idTokenClaims);

        $mockDriver = Mockery::mock(\App\Enterprise\Oidc\Adapters\OAuthOidcDriver::class);
        $mockDriver->shouldReceive('setRedirectUrl')->andReturnSelf();
        $mockDriver->shouldReceive('getUser')->andReturn($socialiteUser);
        $mockDriver->shouldReceive('getIdToken')->andReturn($idToken);

        $mockConnectionManager = Mockery::mock(\App\Enterprise\Oidc\ConnectionManager::class);
        $mockConnectionManager->shouldReceive('getConnectionBySlug')
            ->with('state-disabled')
            ->andReturn($connection);
        $mockConnectionManager->shouldReceive('buildDriver')
            ->with($connection)
            ->andReturn($mockDriver);

        $mockIdTokenVerifier = Mockery::mock(\App\Enterprise\Oidc\IdTokenVerifier::class);
        $mockIdTokenVerifier->shouldReceive('verifySignature')
            ->with($connection, $idToken)
            ->andReturnNull();

        $this->app->instance(\App\Enterprise\Oidc\ConnectionManager::class, $mockConnectionManager);
        $this->app->instance(\App\Enterprise\Oidc\IdTokenVerifier::class, $mockIdTokenVerifier);

        $response = $this->getJson("/auth/{$connection->slug}/callback", [
            'Accept' => 'application/json',
        ]);

        $response->assertSuccessful();
    });

    it('accepts callback when state is valid', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'state-valid',
            'enabled' => true,
        ]);

        $state = 'state-token-12345678';
        $stateVerifier = cacheOidcState($connection, $state);

        $socialiteUser = createMockSocialiteUser(
            email: 'stateuser@example.com',
            name: 'State User'
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-state');
        $idToken = createMockIdToken($idTokenClaims);

        $mockDriver = Mockery::mock(\App\Enterprise\Oidc\Adapters\OAuthOidcDriver::class);
        $mockDriver->shouldReceive('setRedirectUrl')->andReturnSelf();
        $mockDriver->shouldReceive('getUser')->andReturn($socialiteUser);
        $mockDriver->shouldReceive('getIdToken')->andReturn($idToken);

        $mockConnectionManager = Mockery::mock(\App\Enterprise\Oidc\ConnectionManager::class);
        $mockConnectionManager->shouldReceive('getConnectionBySlug')
            ->with('state-valid')
            ->andReturn($connection);
        $mockConnectionManager->shouldReceive('buildDriver')
            ->with($connection)
            ->andReturn($mockDriver);

        $mockIdTokenVerifier = Mockery::mock(\App\Enterprise\Oidc\IdTokenVerifier::class);
        $mockIdTokenVerifier->shouldReceive('verifySignature')
            ->with($connection, $idToken)
            ->andReturnNull();

        $this->app->instance(\App\Enterprise\Oidc\ConnectionManager::class, $mockConnectionManager);
        $this->app->instance(\App\Enterprise\Oidc\IdTokenVerifier::class, $mockIdTokenVerifier);

        $response = $this->getJson("/auth/{$connection->slug}/callback?state={$state}", [
            ...oidcVerifierHeaders($stateVerifier),
        ]);

        $response->assertSuccessful();
        expect(Cache::get("oidc_login_state:{$state}"))->toBeNull();
    });

    it('returns 404 for non-existent connection', function () {
        $mockConnectionManager = Mockery::mock(\App\Enterprise\Oidc\ConnectionManager::class);
        $mockConnectionManager->shouldReceive('getConnectionBySlug')
            ->with('non-existent')
            ->andReturn(null);

        $this->app->instance(\App\Enterprise\Oidc\ConnectionManager::class, $mockConnectionManager);

        $response = $this->getJson("/auth/non-existent/callback", [
            'Accept' => 'application/json',
        ]);

        $response->assertNotFound();
    });

    it('returns error JSON when provisioning fails', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'test-sso',
            'enabled' => true,
        ]);

        $socialiteUser = createMockSocialiteUser(
            email: 'test@example.com', // Provide email to avoid early failure
            name: 'Test User'
        );
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-error');
        $idToken = createMockIdToken($idTokenClaims);

        $mockDriver = Mockery::mock(\App\Enterprise\Oidc\Adapters\OAuthOidcDriver::class);
        $mockDriver->shouldReceive('setRedirectUrl')->andReturnSelf();
        $mockDriver->shouldReceive('getUser')->andReturn($socialiteUser);
        $mockDriver->shouldReceive('getIdToken')->andReturn($idToken);

        $mockConnectionManager = Mockery::mock(\App\Enterprise\Oidc\ConnectionManager::class);
        $mockConnectionManager->shouldReceive('getConnectionBySlug')
            ->with('test-sso')
            ->andReturn($connection);
        $mockConnectionManager->shouldReceive('buildDriver')
            ->with($connection)
            ->andReturn($mockDriver);

        // Mock IdTokenVerifier to skip signature verification in tests
        $mockIdTokenVerifier = Mockery::mock(\App\Enterprise\Oidc\IdTokenVerifier::class);
        $mockIdTokenVerifier->shouldReceive('verifySignature')
            ->with($connection, $idToken)
            ->andReturnNull();

        // Mock ProvisioningService to throw exception (simulating provisioning error)
        $mockProvisioningService = Mockery::mock(\App\Enterprise\Oidc\ProvisioningService::class);
        $mockProvisioningService->shouldReceive('provisionUser')
            ->andThrow(new \Exception('Provisioning failed: Invalid claims'));

        $this->app->instance(\App\Enterprise\Oidc\ConnectionManager::class, $mockConnectionManager);
        $this->app->instance(\App\Enterprise\Oidc\IdTokenVerifier::class, $mockIdTokenVerifier);
        $this->app->instance(\App\Enterprise\Oidc\ProvisioningService::class, $mockProvisioningService);

        $state = 'provisioning-error-state-token';
        $stateVerifier = cacheOidcState($connection, $state);

        $response = $this->getJson("/auth/test-sso/callback?state={$state}", [
            ...oidcVerifierHeaders($stateVerifier),
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
        ]);
        expect($response->json('message'))->toContain('Provisioning failed');
    });

    it('blocks blocked users', function () {
        // Ensure we're not in production mode (which requires HTTPS)
        $originalEnv = config('app.env');
        config(['app.env' => 'testing']);

        $connection = IdentityConnection::factory()->create([
            'slug' => 'test-sso',
            'enabled' => true,
        ]);
        $connection->refresh(); // Ensure all fields are loaded

        $user = $this->createUser([
            'email' => 'blocked@example.com',
            'blocked_at' => now(),
        ]);
        $user->refresh(); // Ensure blocked_at is loaded

        $socialiteUser = createMockSocialiteUser(
            email: 'blocked@example.com',
            name: 'Blocked User'
        );
        // Create ID token claims AFTER connection is created and refreshed
        $idTokenClaims = createValidIdTokenClaims($connection, 'sub-blocked');
        $idTokenClaims['email'] = 'blocked@example.com'; // Ensure email is in claims
        $idToken = createMockIdToken($idTokenClaims);

        // Create UserIdentity for existing user - this should allow provisioning to find the user
        $userIdentity = \App\Enterprise\Oidc\Models\UserIdentity::factory()->create([
            'user_id' => $user->id,
            'connection_id' => $connection->id,
            'subject' => 'sub-blocked',
            'email' => 'blocked@example.com',
            'claims' => $idTokenClaims,
        ]);
        // Ensure the relationship is loaded
        $userIdentity->load('user');

        $mockDriver = Mockery::mock(\App\Enterprise\Oidc\Adapters\OAuthOidcDriver::class);
        $mockDriver->shouldReceive('setRedirectUrl')->andReturnSelf();
        $mockDriver->shouldReceive('getUser')->andReturn($socialiteUser);
        $mockDriver->shouldReceive('getIdToken')->andReturn($idToken);

        $mockConnectionManager = Mockery::mock(\App\Enterprise\Oidc\ConnectionManager::class);
        $mockConnectionManager->shouldReceive('getConnectionBySlug')
            ->with('test-sso')
            ->andReturn($connection);
        $mockConnectionManager->shouldReceive('buildDriver')
            ->with($connection)
            ->andReturn($mockDriver);

        // Mock IdTokenVerifier to skip signature verification in tests
        $mockIdTokenVerifier = Mockery::mock(\App\Enterprise\Oidc\IdTokenVerifier::class);
        $mockIdTokenVerifier->shouldReceive('verifySignature')
            ->with($connection, $idToken)
            ->andReturnNull();

        // Mock ProvisioningService to return the blocked user directly
        // Refresh user to ensure blocked_at is loaded
        $user->refresh();
        $mockProvisioningService = Mockery::mock(\App\Enterprise\Oidc\ProvisioningService::class);
        $mockProvisioningService->shouldReceive('provisionUser')
            ->andReturn($user);

        $this->app->instance(\App\Enterprise\Oidc\ConnectionManager::class, $mockConnectionManager);
        $this->app->instance(\App\Enterprise\Oidc\IdTokenVerifier::class, $mockIdTokenVerifier);
        $this->app->instance(\App\Enterprise\Oidc\ProvisioningService::class, $mockProvisioningService);

        $state = 'blocked-user-state-token';
        $stateVerifier = cacheOidcState($connection, $state);

        $response = $this->getJson("/auth/test-sso/callback?state={$state}", [
            ...oidcVerifierHeaders($stateVerifier),
        ]);

        $response->assertForbidden();
        expect($response->json('message'))->toContain('blocked');

        // Restore original env
        config(['app.env' => $originalEnv]);
    });
});
