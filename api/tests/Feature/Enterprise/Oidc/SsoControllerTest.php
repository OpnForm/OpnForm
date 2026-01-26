<?php

use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Tests\TestHelpers;

require_once __DIR__ . '/../../../TestHelpers/OidcTestHelpers.php';

uses(TestHelpers::class);
uses()->group('oidc', 'feature');

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

        // Mock the driver to return a redirect URL
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

        $response = $this->postJson("/auth/test-sso/redirect");

        $response->assertSuccessful();
        $response->assertJson([
            'redirect_url' => 'https://idp.example.com/authorize',
        ]);
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

        $response = $this->getJson("/auth/test-sso/callback", [
            'Accept' => 'application/json',
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

    it('rejects callback when state is required but missing', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'state-required',
            'enabled' => true,
            'options' => [
                'require_state' => true,
            ],
        ]);

        $response = $this->getJson("/auth/{$connection->slug}/callback", [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(400);
        expect($response->json('message'))->toContain('Missing or invalid state');
    });

    it('accepts callback when state is valid and required', function () {
        $connection = IdentityConnection::factory()->create([
            'slug' => 'state-valid',
            'enabled' => true,
            'options' => [
                'require_state' => true,
            ],
        ]);

        $state = 'state-token-12345678';
        \Illuminate\Support\Facades\Cache::put("oidc_state_{$state}", true, 600);

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
            'Accept' => 'application/json',
        ]);

        $response->assertSuccessful();
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

        $response = $this->getJson("/auth/test-sso/callback", [
            'Accept' => 'application/json',
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

        $response = $this->getJson("/auth/test-sso/callback", [
            'Accept' => 'application/json',
        ]);

        $response->assertForbidden();
        expect($response->json('message'))->toContain('blocked');

        // Restore original env
        config(['app.env' => $originalEnv]);
    });
});
