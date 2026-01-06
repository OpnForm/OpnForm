<?php

use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Models\User;
use App\Models\Workspace;

uses()->group('oidc', 'feature');

describe('OidcConnectionController - List Connections', function () {
    it('lists workspace-scoped connections for workspace admin', function () {
        $user = $this->actingAsUser();
        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $connection = IdentityConnection::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $response = $this->getJson("/open/workspaces/{$workspace->id}/oidc-connections");

        $response->assertSuccessful();
        $response->assertJsonCount(1);
        expect($response->json('0.id'))->toBe($connection->id);
    });

    it('prevents workspace member from listing connections', function () {
        $user = $this->actingAsUser();
        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'member']);

        IdentityConnection::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $response = $this->getJson("/open/workspaces/{$workspace->id}/oidc-connections");

        // Only workspace admins can view connections
        $response->assertForbidden();
    });

    it('includes workspace-scoped and global connections for workspace', function () {
        $user = $this->actingAsUser();
        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $workspaceConnection = IdentityConnection::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $globalConnection = IdentityConnection::factory()->create([
            'workspace_id' => null,
        ]);

        $otherWorkspace = Workspace::factory()->create();
        IdentityConnection::factory()->create([
            'workspace_id' => $otherWorkspace->id,
        ]);

        $response = $this->getJson("/open/workspaces/{$workspace->id}/oidc-connections");

        $response->assertSuccessful();
        $response->assertJsonCount(2);
        $ids = collect($response->json())->pluck('id')->toArray();
        expect($ids)->toContain($workspaceConnection->id);
        expect($ids)->toContain($globalConnection->id);
    });
});

describe('OidcConnectionController - Create Connection', function () {
    beforeEach(function () {
        // Set self-hosted mode to bypass Pro checks in tests
        config(['app.self_hosted' => true]);
    });

    it('creates workspace-scoped connection', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $data = [
            'name' => 'Company SSO',
            'slug' => 'company-sso',
            'domain' => 'company.com',
            'issuer' => 'https://idp.company.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
            'enabled' => true,
        ];

        $response = $this->postJson("/open/workspaces/{$workspace->id}/oidc-connections", $data);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'name',
            'slug',
            'domain',
            'issuer',
            'client_id',
            'redirect_url',
            'enabled',
        ]);

        expect($response->json('name'))->toBe('Company SSO');
        expect($response->json('workspace_id'))->toBe($workspace->id);
        expect($response->json('client_secret'))->toBeNull(); // Should not expose secret
    });

    it('validates required fields', function () {
        $user = $this->actingAsUser();
        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $response = $this->postJson("/open/workspaces/{$workspace->id}/oidc-connections", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'slug', 'domain', 'issuer', 'client_id', 'client_secret']);
    });

    it('validates unique slug', function () {
        $user = $this->actingAsUser();
        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        IdentityConnection::factory()->create(['slug' => 'existing-slug']);

        $data = [
            'name' => 'Test',
            'slug' => 'existing-slug',
            'domain' => 'test.com',
            'issuer' => 'https://idp.test.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
        ];

        $response = $this->postJson("/open/workspaces/{$workspace->id}/oidc-connections", $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug']);
    });

    it('validates unique domain per workspace', function () {
        $user = $this->actingAsUser();
        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        IdentityConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'domain' => 'company.com',
        ]);

        $data = [
            'name' => 'Test',
            'slug' => 'test-slug',
            'domain' => 'company.com',
            'issuer' => 'https://idp.test.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
        ];

        $response = $this->postJson("/open/workspaces/{$workspace->id}/oidc-connections", $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['domain']);
    });

    it('enforces single connection per workspace', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        IdentityConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => IdentityConnection::TYPE_OIDC,
            'domain' => 'company.com',
        ]);

        // Try to create second connection with different domain (should still fail)
        $data = [
            'name' => 'Second Connection',
            'slug' => 'second-connection',
            'domain' => 'mail.company.com', // Different domain, but same workspace
            'issuer' => 'https://idp.company.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
        ];

        $response = $this->postJson("/open/workspaces/{$workspace->id}/oidc-connections", $data);

        $response->assertStatus(422);
        expect($response->json('message'))->toContain('already has an OIDC connection');
    });

    it('validates domain matches user email domain', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $data = [
            'name' => 'Company SSO',
            'slug' => 'company-sso',
            'domain' => 'company.com',
            'issuer' => 'https://idp.company.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
        ];

        $response = $this->postJson("/open/workspaces/{$workspace->id}/oidc-connections", $data);

        $response->assertStatus(201);
    });

    it('rejects domain that does not match user email domain', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $data = [
            'name' => 'Test SSO',
            'slug' => 'test-sso',
            'domain' => 'other.com',
            'issuer' => 'https://idp.other.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
        ];

        $response = $this->postJson("/open/workspaces/{$workspace->id}/oidc-connections", $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['domain']);
        expect($response->json('errors.domain.0'))->toContain('must match your email domain');
    });

    it('rejects blocked email providers', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $data = [
            'name' => 'Test SSO',
            'slug' => 'test-sso',
            'domain' => 'gmail.com',
            'issuer' => 'https://idp.gmail.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
        ];

        $response = $this->postJson("/open/workspaces/{$workspace->id}/oidc-connections", $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['domain']);
        expect($response->json('errors.domain.0'))->toContain('Common email providers');
    });

    it('allows root domain match when user has subdomain email', function () {
        $user = User::factory()->create(['email' => 'user@mail.company.com']);
        $this->actingAs($user);

        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $data = [
            'name' => 'Company SSO',
            'slug' => 'company-sso',
            'domain' => 'company.com',
            'issuer' => 'https://idp.company.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
        ];

        $response = $this->postJson("/open/workspaces/{$workspace->id}/oidc-connections", $data);

        $response->assertStatus(201);
    });

    it('allows subdomain match when user has root domain email', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $data = [
            'name' => 'Company SSO',
            'slug' => 'company-sso',
            'domain' => 'mail.company.com',
            'issuer' => 'https://idp.company.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
        ];

        $response = $this->postJson("/open/workspaces/{$workspace->id}/oidc-connections", $data);

        $response->assertStatus(201);
    });

    it('validates domain on update', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $connection = IdentityConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'domain' => 'company.com',
        ]);

        // Try to update to a different domain
        $response = $this->patchJson(
            "/open/workspaces/{$workspace->id}/oidc-connections/{$connection->id}",
            ['domain' => 'other.com']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['domain']);
    });

    it('allows updating to matching domain', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $connection = IdentityConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'domain' => 'company.com',
        ]);

        // Update to subdomain of same root domain
        $response = $this->patchJson(
            "/open/workspaces/{$workspace->id}/oidc-connections/{$connection->id}",
            ['domain' => 'mail.company.com']
        );

        $response->assertSuccessful();
        expect($connection->fresh()->domain)->toBe('mail.company.com');
    });
});

describe('OidcConnectionController - Update Connection', function () {
    beforeEach(function () {
        // Set self-hosted mode to bypass Pro checks in tests
        config(['app.self_hosted' => true]);
    });

    it('updates workspace-scoped connection', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $connection = IdentityConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Old Name',
            'domain' => 'company.com',
        ]);

        $response = $this->patchJson(
            "/open/workspaces/{$workspace->id}/oidc-connections/{$connection->id}",
            ['name' => 'New Name']
        );

        $response->assertSuccessful();
        expect($response->json('name'))->toBe('New Name');
        expect($connection->fresh()->name)->toBe('New Name');
    });

    it('does not update client_secret if not provided', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $connection = IdentityConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'client_secret' => 'old-secret',
            'domain' => 'company.com',
        ]);

        $response = $this->patchJson(
            "/open/workspaces/{$workspace->id}/oidc-connections/{$connection->id}",
            ['name' => 'Updated Name']
        );

        $response->assertSuccessful();
        expect($connection->fresh()->client_secret)->toBe('old-secret');
    });

    it('merges options instead of replacing', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $connection = IdentityConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'domain' => 'company.com',
            'options' => [
                'field_mappings' => ['email' => 'mail'],
                'custom_field' => 'custom_value',
            ],
        ]);

        $response = $this->patchJson(
            "/open/workspaces/{$workspace->id}/oidc-connections/{$connection->id}",
            [
                'options' => [
                    'field_mappings' => ['email' => 'email_address'],
                ],
            ]
        );

        $response->assertSuccessful();
        $options = $connection->fresh()->options;
        expect($options['field_mappings']['email'])->toBe('email_address');
        expect($options['custom_field'])->toBe('custom_value'); // Should be preserved
    });
});

describe('OidcConnectionController - Delete Connection', function () {
    beforeEach(function () {
        // Set self-hosted mode to bypass Pro checks in tests
        config(['app.self_hosted' => true]);
    });

    it('deletes workspace-scoped connection', function () {
        $user = User::factory()->create(['email' => 'user@company.com']);
        $this->actingAs($user);

        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $connection = IdentityConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'domain' => 'company.com',
        ]);

        $response = $this->deleteJson("/open/workspaces/{$workspace->id}/oidc-connections/{$connection->id}");

        $response->assertStatus(204);
        expect(IdentityConnection::find($connection->id))->toBeNull();
    });

    it('prevents deletion by non-admin', function () {
        $user = $this->actingAsUser();
        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'member']);

        $connection = IdentityConnection::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $response = $this->deleteJson("/open/workspaces/{$workspace->id}/oidc-connections/{$connection->id}");

        $response->assertStatus(403);
        expect(IdentityConnection::find($connection->id))->not->toBeNull();
    });
});
