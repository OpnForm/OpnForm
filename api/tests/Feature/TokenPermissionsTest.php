<?php

use App\Models\Workspace;
use Laravel\Sanctum\Sanctum;

it('can create token with any user', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);

    $this->postJson(route('settings.tokens.store'), [
        'name' => 'New Token',
        'abilities' => ['forms-read'],
    ])->assertSuccessful();
});


it('can access read-only workspace endpoints with read token', function () {
    $user = $this->createUser();
    $workspace = Workspace::factory()->create();
    $workspace->users()->attach($user, ['role' => 'admin']);

    Sanctum::actingAs($user, ['workspaces-read']);

    $this->getJson(route('open.workspaces.index'))
        ->assertSuccessful();

    $this->postJson(route('open.workspaces.create'), [
        'name' => 'New Workspace',
        'icon' => '🚀',
    ])->assertForbidden();
});

it('can access write workspace endpoints with write token', function () {
    // Arrange: Create a user without any existing workspaces
    // Free users can create one workspace, but not additional ones
    $user = $this->createUser();
    // Don't attach to any workspace - user starts with 0 workspaces

    Sanctum::actingAs($user, ['workspaces-write']);

    $this->postJson(route('open.workspaces.create'), [
        'name' => 'New Workspace',
        'icon' => '🚀',
    ])->assertSuccessful();
});
