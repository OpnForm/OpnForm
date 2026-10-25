<?php

use App\Models\User;
use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Enterprise\Oidc\OidcLinkService;

it('can login to Forms', function () {
    $user = User::factory()->create();

    $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'Abcd@1234',
    ])
        ->assertSuccessful()
        ->assertJsonStructure(['token', 'expires_in'])
        ->assertJson(['token_type' => 'bearer']);
});

it('can fetch current user', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/user')
        ->assertSuccessful()
        ->assertJsonStructure(['id', 'name', 'email']);
});

it('can log out', function () {
    $this->postJson('/login', [
        'email' => User::factory()->create()->email,
        'password' => 'Abcd@1234',
    ])->assertSuccessful();

    $this->assertAuthenticated();
    $this->postJson('/logout')
        ->assertSuccessful();

    $this->assertGuest();
    $this->getJson('/user')
        ->assertStatus(401);
});

it('cannot login if user is blocked', function () {
    $user = User::factory()->create([
        'blocked_at' => now(),
    ]);

    $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'Abcd@1234',
    ])
        ->assertStatus(403)
        ->assertJson(['message' => 'Your account has been blocked. Please contact support.']);

    $this->assertGuest();
});

it('blocks password login when OIDC force_login is enabled', function () {
    // Create an OIDC connection
    IdentityConnection::factory()->create([
        'enabled' => true,
        'type' => IdentityConnection::TYPE_OIDC,
    ]);

    // Enable force_login
    config(['oidc.force_login' => true]);

    $user = User::factory()->create();

    $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'Abcd@1234',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email' => 'Password-based login is disabled. Please use OIDC authentication.']);

    $this->assertGuest();

    // Restore config
    config(['oidc.force_login' => false]);
});

it('allows an existing account to sign in with a valid OIDC link token when force login is enabled', function () {
    $connection = IdentityConnection::factory()->create([
        'enabled' => true,
        'type' => IdentityConnection::TYPE_OIDC,
    ]);
    config(['oidc.force_login' => true]);

    $user = User::factory()->create();
    $linkToken = app(OidcLinkService::class)->createLinkToken(
        connectionId: $connection->id,
        subject: 'existing-account-subject',
        email: $user->email,
        claims: ['email' => $user->email],
    );

    $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'Abcd@1234',
        'oidc_link_token' => $linkToken,
    ])->assertSuccessful();

    config(['oidc.force_login' => false]);
});

it('does not allow a link token for a different account to bypass forced OIDC login', function () {
    $connection = IdentityConnection::factory()->create([
        'enabled' => true,
        'type' => IdentityConnection::TYPE_OIDC,
    ]);
    config(['oidc.force_login' => true]);

    $user = User::factory()->create();
    $linkToken = app(OidcLinkService::class)->createLinkToken(
        connectionId: $connection->id,
        subject: 'different-account-subject',
        email: 'other@example.com',
        claims: ['email' => 'other@example.com'],
    );

    $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'Abcd@1234',
        'oidc_link_token' => $linkToken,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email' => 'Password-based login is disabled. Please use OIDC authentication.']);

    config(['oidc.force_login' => false]);
});

it('allows password login when OIDC force_login is enabled but no connections exist', function () {
    // Enable force_login but no OIDC connections
    config(['oidc.force_login' => true]);

    $user = User::factory()->create();

    $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'Abcd@1234',
    ])
        ->assertSuccessful();

    // Restore config
    config(['oidc.force_login' => false]);
});
