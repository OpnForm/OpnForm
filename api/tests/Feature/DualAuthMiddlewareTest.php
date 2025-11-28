<?php

use App\Models\User;
use App\Models\Workspace;
use Laravel\Sanctum\Sanctum;

it('allows Sanctum token to access whitelisted GET endpoint', function () {
    // Arrange: user with a workspace and a Sanctum token that has read ability
    $user = $this->createUser();
    $workspace = Workspace::factory()->create();
    $workspace->users()->attach($user, ['role' => 'admin']);

    // Act as the user via Sanctum with the relevant read ability
    Sanctum::actingAs($user, ['workspaces-read']);

    // The route name exists in config/sanctum-routes.php so the request should succeed (200)
    $this->getJson(route('open.workspaces.index'))
        ->assertSuccessful();
});

it('returns 404 when Sanctum token hits a non-whitelisted GET endpoint', function () {
    // Arrange: authenticated user via Sanctum
    $user = $this->createUser();
    $workspace = Workspace::factory()->create();
    $workspace->users()->attach($user, ['role' => 'admin']);
    Sanctum::actingAs($user, ['workspaces-read']); // abilities don\'t matter for middleware check

    // `open.providers` is NOT present in the sanctum whitelist, therefore the middleware
    // should short-circuit the request with a 404 response
    $this->getJson(route('open.providers'))
        ->assertNotFound();
});

it('allows JWT token to access whitelisted GET endpoint', function () {
    // Arrange: Authenticate a user via JWT (session-based for tests)
    $this->actingAsUser();

    // Act & Assert: The request should succeed because JWT auth does not check the whitelist.
    $this->getJson(route('open.workspaces.index'))
        ->assertSuccessful();
});

it('allows JWT token to access non-whitelisted GET endpoint', function () {
    // Arrange: Authenticate a user via JWT (session-based for tests)
    $this->actingAsUser();

    // Act & Assert: The request should succeed because JWT auth bypasses the Sanctum route whitelist.
    // The route 'open.providers' is not in sanctum-routes.php.
    $this->getJson(route('open.providers'))
        ->assertSuccessful();
});

it('throws AuthenticationException for unauthenticated requests', function () {
    // Act & Assert: Attempt to access a protected route without any authentication.
    // The middleware should throw an AuthenticationException, resulting in a 401.
    $this->getJson(route('open.workspaces.index'))
        ->assertUnauthorized();
});
