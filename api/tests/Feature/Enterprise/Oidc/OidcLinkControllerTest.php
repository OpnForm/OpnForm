<?php

use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Enterprise\Oidc\Models\UserIdentity;
use App\Enterprise\Oidc\OidcLinkService;
use Illuminate\Support\Facades\Cache;
use Tests\TestHelpers;

uses(TestHelpers::class);
uses()->group('oidc', 'feature');

afterEach(function () {
    Cache::flush();
});

describe('OidcLinkController', function () {
    it('completes an account link after the one-time password login allowed by forced OIDC', function () {
        $connection = IdentityConnection::factory()->create([
            'enabled' => true,
            'type' => IdentityConnection::TYPE_OIDC,
        ]);
        $user = $this->createUser();
        $linkToken = app(OidcLinkService::class)->createLinkToken(
            connectionId: $connection->id,
            subject: 'existing-account-subject',
            email: $user->email,
            claims: ['email' => $user->email],
        );
        config(['oidc.force_login' => true]);

        $loginResponse = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'Abcd@1234',
            'oidc_link_token' => $linkToken,
        ])->assertSuccessful();

        $this->withToken($loginResponse->json('token'))
            ->postJson('/auth/oidc/link', [
                'link_token' => $linkToken,
            ])
            ->assertSuccessful()
            ->assertJson(['linked' => true]);

        $this->assertDatabaseHas('user_identities', [
            'user_id' => $user->id,
            'connection_id' => $connection->id,
            'subject' => 'existing-account-subject',
            'email' => $user->email,
        ]);

        config(['oidc.force_login' => false]);
    });

    it('links an existing account with a valid token', function () {
        $user = $this->actingAsUser();
        $connection = IdentityConnection::factory()->create();

        $linkToken = app(OidcLinkService::class)->createLinkToken(
            connectionId: $connection->id,
            subject: 'sub-123',
            email: $user->email,
            claims: ['email' => $user->email]
        );

        $response = $this->postJson('/auth/oidc/link', [
            'link_token' => $linkToken,
        ]);

        $response->assertSuccessful();
        $response->assertJson([
            'linked' => true,
        ]);

        $this->assertDatabaseHas('user_identities', [
            'user_id' => $user->id,
            'connection_id' => $connection->id,
            'subject' => 'sub-123',
            'email' => $user->email,
        ]);
    });

    it('rejects expired or unknown tokens', function () {
        $this->actingAsUser();

        $response = $this->postJson('/auth/oidc/link', [
            'link_token' => 'missing-token',
        ]);

        $response->assertStatus(410);
        $response->assertJson([
            'error' => 'oidc_link_expired',
        ]);
    });

    it('rejects when the token email does not match the logged in user', function () {
        $user = $this->actingAsUser();
        $connection = IdentityConnection::factory()->create();

        $linkToken = app(OidcLinkService::class)->createLinkToken(
            connectionId: $connection->id,
            subject: 'sub-456',
            email: 'other@example.com',
            claims: ['email' => 'other@example.com']
        );

        $response = $this->postJson('/auth/oidc/link', [
            'link_token' => $linkToken,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'oidc_link_email_mismatch',
        ]);
    });

    it('rejects when the subject is already linked to another user', function () {
        $user = $this->actingAsUser();
        $otherUser = $this->createUser();
        $connection = IdentityConnection::factory()->create();

        UserIdentity::factory()->create([
            'user_id' => $otherUser->id,
            'connection_id' => $connection->id,
            'subject' => 'sub-789',
            'email' => $otherUser->email,
        ]);

        $linkToken = app(OidcLinkService::class)->createLinkToken(
            connectionId: $connection->id,
            subject: 'sub-789',
            email: $user->email,
            claims: ['email' => $user->email]
        );

        $response = $this->postJson('/auth/oidc/link', [
            'link_token' => $linkToken,
        ]);

        $response->assertStatus(409);
        $response->assertJson([
            'error' => 'oidc_link_already_linked',
        ]);
    });
});
