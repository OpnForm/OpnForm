<?php

use App\Models\License;
use App\Models\UserInvite;
use App\Service\License\LicenseCheckResult;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config(['app.self_hosted' => true]);
    config(['cashier.key' => null]);
    $this->user = $this->actingAsUser();
    $this->workspace = $this->createUserWorkspace($this->user);
});

describe('self-hosted invite limits without license', function () {
    it('allows first invite when no existing invites', function () {
        $response = $this->postJson(
            route('open.workspaces.users.add', ['workspace' => $this->workspace]),
            ['email' => 'user2@example.com', 'role' => 'user']
        );

        $response->assertSuccessful();
    });

    it('allows second invite when only one existing invite', function () {
        UserInvite::create([
            'email' => 'first@example.com',
            'role' => 'user',
            'workspace_id' => $this->workspace->id,
            'token' => 'test_token_' . str_repeat('a', 90),
            'status' => UserInvite::PENDING_STATUS,
            'valid_until' => now()->addDays(7),
        ]);

        $response = $this->postJson(
            route('open.workspaces.users.add', ['workspace' => $this->workspace]),
            ['email' => 'user3@example.com', 'role' => 'user']
        );

        $response->assertSuccessful();
    });

    it('blocks third invite when already 2 invites exist', function () {
        for ($i = 1; $i <= 2; $i++) {
            UserInvite::create([
                'email' => "existing{$i}@example.com",
                'role' => 'user',
                'workspace_id' => $this->workspace->id,
                'token' => 'test_token_' . str_repeat((string) $i, 90),
                'status' => UserInvite::PENDING_STATUS,
                'valid_until' => now()->addDays(7),
            ]);
        }

        $response = $this->postJson(
            route('open.workspaces.users.add', ['workspace' => $this->workspace]),
            ['email' => 'user4@example.com', 'role' => 'user']
        );

        $response->assertStatus(403);
    });
});

describe('self-hosted invite limits with valid license', function () {
    beforeEach(function () {
        License::create([
            'license_key' => 'lic_enterprise12345',
            'license_provider' => 'self_hosted_enterprise',
            'status' => 'active',
            'meta' => [],
            'features' => ['sso' => true, 'multiOrg' => true],
            'last_checked_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        Cache::put('self_hosted_license_check', new LicenseCheckResult(
            status: 'active',
            features: ['sso' => true, 'multiOrg' => true],
            lastChecked: now(),
            expiresAt: now()->addYear(),
        ), 86400);
    });

    it('allows inviting beyond 2 with valid license', function () {
        for ($i = 1; $i <= 3; $i++) {
            UserInvite::create([
                'email' => "existing{$i}@example.com",
                'role' => 'user',
                'workspace_id' => $this->workspace->id,
                'token' => 'test_token_' . str_repeat((string) $i, 90),
                'status' => UserInvite::PENDING_STATUS,
                'valid_until' => now()->addDays(7),
            ]);
        }

        $response = $this->postJson(
            route('open.workspaces.users.add', ['workspace' => $this->workspace]),
            ['email' => 'user5@example.com', 'role' => 'user']
        );

        $response->assertSuccessful();
    });
});

describe('self-hosted invite with expired license', function () {
    it('blocks inviting beyond 2 when license is expired', function () {
        License::create([
            'license_key' => 'lic_expired12345',
            'license_provider' => 'self_hosted_enterprise',
            'status' => 'inactive',
            'meta' => [],
            'features' => null,
            'last_checked_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
        ]);

        for ($i = 1; $i <= 2; $i++) {
            UserInvite::create([
                'email' => "existing{$i}@example.com",
                'role' => 'user',
                'workspace_id' => $this->workspace->id,
                'token' => 'test_token_' . str_repeat((string) $i, 90),
                'status' => UserInvite::PENDING_STATUS,
                'valid_until' => now()->addDays(7),
            ]);
        }

        $response = $this->postJson(
            route('open.workspaces.users.add', ['workspace' => $this->workspace]),
            ['email' => 'user4@example.com', 'role' => 'user']
        );

        $response->assertStatus(403);
    });
});
