<?php

namespace Tests\TestHelpers;

use App\Models\User;
use App\Models\UserInvite;
use App\Models\Workspace;
use App\Models\OAuthProvider;
use Illuminate\Support\Facades\Cache;

trait OAuthTestHelpers
{
    /**
     * Create a user invite for testing
     */
    public function createUserInvite(array $data = []): UserInvite
    {
        $workspace = Workspace::factory()->create();

        return UserInvite::factory()->create(array_merge([
            'email' => 'invitee@example.com',
            'role' => User::ROLE_USER,
            'workspace_id' => $workspace->id,
            'status' => UserInvite::PENDING_STATUS,
            'valid_until' => now()->addDays(7),
        ], $data));
    }

    /**
     * Create an expired user invite
     */
    public function createExpiredUserInvite(array $data = []): UserInvite
    {
        return $this->createUserInvite(array_merge([
            'valid_until' => now()->subDays(1),
        ], $data));
    }

    /**
     * Create an accepted user invite
     */
    public function createAcceptedUserInvite(array $data = []): UserInvite
    {
        return $this->createUserInvite(array_merge([
            'status' => UserInvite::ACCEPTED_STATUS,
        ], $data));
    }

    /**
     * Create an OAuth provider record
     */
    public function createOAuthProvider(User $user, array $data = []): OAuthProvider
    {
        return OAuthProvider::factory()->create(array_merge([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google123',
            'name' => 'John Doe',
            'email' => $user->email,
            'avatar' => 'https://example.com/avatar.jpg',
        ], $data));
    }

    /**
     * Create OAuth context in cache for testing
     */
    public function createOAuthContext(array $context = []): string
    {
        $stateToken = bin2hex(random_bytes(16));
        $key = "oauth-context:state:" . $stateToken;

        $defaultContext = [
            'intent' => 'auth',
            'utm_data' => null,
            'invited_email' => null,
            'invite_token' => null,
            'intention' => null,
            'autoClose' => false,
        ];

        Cache::put($key, array_merge($defaultContext, $context), now()->addMinutes(5));

        return $stateToken;
    }

    /**
     * Assert OAuth context exists in cache
     */
    public function assertOAuthContextExists(string $stateToken): void
    {
        $key = "oauth-context:state:" . $stateToken;
        $this->assertTrue(Cache::has($key), "OAuth context should exist for state token: {$stateToken}");
    }

    /**
     * Assert OAuth context was cleared from cache
     */
    public function assertOAuthContextCleared(string $stateToken): void
    {
        $key = "oauth-context:state:" . $stateToken;
        $this->assertFalse(Cache::has($key), "OAuth context should be cleared for state token: {$stateToken}");
    }

    /**
     * Create realistic OAuth callback parameters
     */
    public function createOAuthCallbackParams(array $params = []): array
    {
        return array_merge([
            'code' => 'oauth_code_123',
            'state' => 'state_token_123',
            'scope' => 'profile email',
        ], $params);
    }

    /**
     * Create realistic widget callback data
     */
    public function createWidgetCallbackData(array $data = []): array
    {
        return array_merge([
            'credential' => 'jwt_token_here',
            'g_csrf_token' => 'csrf_token_here',
            'intent' => 'auth',
        ], $data);
    }

    /**
     * Assert redirect response has proper OAuth URL structure
     */
    public function assertValidOAuthRedirectResponse($response): void
    {
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'url',
            'state'
        ]);

        $data = $response->json();
        $this->assertStringContainsString('http', $data['url']);
        $this->assertNotEmpty($data['state']);
    }

    /**
     * Assert OAuth callback response has proper JWT structure
     */
    public function assertValidOAuthCallbackResponse($response): void
    {
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in'
        ]);
    }
}
