<?php

use App\Models\UserInvite;
use App\Service\License\LicenseCheckResult;
use App\Service\WorkspaceInviteService;
use Illuminate\Http\Exceptions\HttpResponseException;
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

    it('blocks a second pending invite because owner plus one invite uses the free seats', function () {
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

        $response->assertStatus(403);
    });

    it('blocks invites in another workspace when the instance free seats are already reserved', function () {
        UserInvite::create([
            'email' => 'first@example.com',
            'role' => 'user',
            'workspace_id' => $this->workspace->id,
            'token' => 'test_token_' . str_repeat('c', 90),
            'status' => UserInvite::PENDING_STATUS,
            'valid_until' => now()->addDays(7),
        ]);

        $otherWorkspace = $this->createUserWorkspace($this->user);

        $response = $this->postJson(
            route('open.workspaces.users.add', ['workspace' => $otherWorkspace]),
            ['email' => 'another@example.com', 'role' => 'user']
        );

        $response->assertStatus(403);
    });

    it('does not count expired pending invites toward the instance free seats', function () {
        UserInvite::create([
            'email' => 'expired@example.com',
            'role' => 'user',
            'workspace_id' => $this->workspace->id,
            'token' => 'test_token_' . str_repeat('e', 90),
            'status' => UserInvite::PENDING_STATUS,
            'valid_until' => now()->subDay(),
        ]);

        $response = $this->postJson(
            route('open.workspaces.users.add', ['workspace' => $this->workspace]),
            ['email' => 'fresh@example.com', 'role' => 'user']
        );

        $response->assertSuccessful();
    });

    it('does not treat a differently-cased pending invite email as another free seat', function () {
        UserInvite::create([
            'email' => 'MixedCase@example.com',
            'role' => 'user',
            'workspace_id' => $this->workspace->id,
            'token' => 'test_token_' . str_repeat('m', 90),
            'status' => UserInvite::PENDING_STATUS,
            'valid_until' => now()->addDays(7),
        ]);

        $otherWorkspace = $this->createUserWorkspace($this->user);

        $response = $this->postJson(
            route('open.workspaces.users.add', ['workspace' => $otherWorkspace]),
            ['email' => 'mixedcase@example.com', 'role' => 'user']
        );

        $response->assertSuccessful();
    });

    it('blocks adding a third active user', function () {
        $member = $this->createUser(['email' => 'member@example.com']);
        $this->workspace->users()->attach($member, ['role' => 'user']);

        $response = $this->postJson(
            route('open.workspaces.users.add', ['workspace' => $this->workspace]),
            ['email' => 'user3@example.com', 'role' => 'user']
        );

        $response->assertStatus(403);
    });

    it('allows adding an existing instance user to another workspace at the free seat limit', function () {
        $member = $this->createUser(['email' => 'member@example.com']);
        $this->workspace->users()->attach($member, ['role' => 'user']);
        $otherWorkspace = $this->createUserWorkspace($this->user);

        $response = $this->postJson(
            route('open.workspaces.users.add', ['workspace' => $otherWorkspace]),
            ['email' => 'member@example.com', 'role' => 'user']
        );

        $response->assertSuccessful();
        expect($otherWorkspace->users()->whereKey($member->id)->exists())->toBeTrue();
    });

    it('allows accepting the first invited free seat', function () {
        $invite = UserInvite::create([
            'email' => 'first@example.com',
            'role' => 'user',
            'workspace_id' => $this->workspace->id,
            'token' => 'test_token_' . str_repeat('a', 90),
            'status' => UserInvite::PENDING_STATUS,
            'valid_until' => now()->addDays(7),
        ]);

        [$workspace, $role] = app(WorkspaceInviteService::class)->getWorkspaceAndRole([
            'email' => $invite->email,
            'invite_token' => $invite->token,
        ]);

        expect($workspace->id)->toBe($this->workspace->id);
        expect($role)->toBe('user');
        expect($invite->refresh()->status)->toBe(UserInvite::ACCEPTED_STATUS);
    });

    it('blocks accepting an invite when the free seats are already filled', function () {
        $member = $this->createUser(['email' => 'member@example.com']);
        $this->workspace->users()->attach($member, ['role' => 'user']);

        $invite = UserInvite::create([
            'email' => 'blocked@example.com',
            'role' => 'user',
            'workspace_id' => $this->workspace->id,
            'token' => 'test_token_' . str_repeat('b', 90),
            'status' => UserInvite::PENDING_STATUS,
            'valid_until' => now()->addDays(7),
        ]);

        expect(fn () => app(WorkspaceInviteService::class)->getWorkspaceAndRole([
            'email' => $invite->email,
            'invite_token' => $invite->token,
        ]))->toThrow(HttpResponseException::class);

        expect($invite->refresh()->status)->toBe(UserInvite::PENDING_STATUS);
    });
});

describe('self-hosted invite limits with valid license', function () {
    beforeEach(function () {
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_enterprise12345',
            'status' => 'active',
            'features' => ['sso' => true, 'multiOrg' => true],
            'last_checked_at' => now()->format('c'),
            'expires_at' => now()->addYear()->format('c'),
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
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_expired12345',
            'status' => 'expired',
            'features' => null,
            'last_checked_at' => now()->subDays(2)->format('c'),
            'expires_at' => now()->subDay()->format('c'),
        ]);
        Cache::put('self_hosted_license_check', new LicenseCheckResult(
            status: 'expired',
            features: null,
            lastChecked: now()->subDays(2),
            expiresAt: now()->subDay(),
        ), 86400);

        UserInvite::create([
            'email' => 'existing@example.com',
            'role' => 'user',
            'workspace_id' => $this->workspace->id,
            'token' => 'test_token_' . str_repeat('1', 90),
            'status' => UserInvite::PENDING_STATUS,
            'valid_until' => now()->addDays(7),
        ]);

        $response = $this->postJson(
            route('open.workspaces.users.add', ['workspace' => $this->workspace]),
            ['email' => 'user4@example.com', 'role' => 'user']
        );

        $response->assertStatus(403);
    });
});
