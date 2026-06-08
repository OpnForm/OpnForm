<?php

use App\Models\UserInvite;
use Laravel\Sanctum\Sanctum;

describe('Invite listing authorization', function () {
    it('allows admin to list invites', function () {
        $admin = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($admin);
        UserInvite::inviteUser('test@example.com', 'user', $workspace);

        $this->getJson(route('open.workspaces.invites.index', $workspace))
            ->assertSuccessful();
    });

    it('denies non-admin user role from listing invites', function () {
        $admin = $this->createProUser();
        $workspace = $this->createUserWorkspace($admin);
        UserInvite::inviteUser('test@example.com', 'user', $workspace);

        $member = $this->createUser();
        $workspace->users()->attach($member, ['role' => 'user']);
        $this->actingAsUser($member);

        $this->getJson(route('open.workspaces.invites.index', $workspace))
            ->assertStatus(403);
    });

    it('denies readonly role from listing invites', function () {
        $admin = $this->createProUser();
        $workspace = $this->createUserWorkspace($admin);
        UserInvite::inviteUser('test@example.com', 'user', $workspace);

        $readonly = $this->createUser();
        $workspace->users()->attach($readonly, ['role' => 'readonly']);
        $this->actingAsUser($readonly);

        $this->getJson(route('open.workspaces.invites.index', $workspace))
            ->assertStatus(403);
    });

    it('allows admin token with workspaces-write ability to list invites', function () {
        $admin = $this->createProUser();
        $workspace = $this->createUserWorkspace($admin);
        UserInvite::inviteUser('test@example.com', 'user', $workspace);

        Sanctum::actingAs($admin, ['workspaces-write']);

        $this->getJson(route('open.workspaces.invites.index', $workspace))
            ->assertSuccessful();
    });

    it('denies admin token with workspace-users-read ability from listing invites', function () {
        $admin = $this->createProUser();
        $workspace = $this->createUserWorkspace($admin);
        UserInvite::inviteUser('test@example.com', 'user', $workspace);

        Sanctum::actingAs($admin, ['workspace-users-read']);

        $this->getJson(route('open.workspaces.invites.index', $workspace))
            ->assertStatus(403);
    });

    it('denies non-admin token with workspaces-write ability from listing invites', function () {
        $admin = $this->createProUser();
        $workspace = $this->createUserWorkspace($admin);
        UserInvite::inviteUser('test@example.com', 'user', $workspace);

        $member = $this->createUser();
        $workspace->users()->attach($member, ['role' => 'user']);
        Sanctum::actingAs($member, ['workspaces-write']);

        $this->getJson(route('open.workspaces.invites.index', $workspace))
            ->assertStatus(403);
    });
});

describe('Invite token redaction', function () {
    it('does not expose token in invite listing', function () {
        $admin = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($admin);
        UserInvite::inviteUser('test@example.com', 'user', $workspace);

        $response = $this->getJson(route('open.workspaces.invites.index', $workspace))
            ->assertSuccessful();

        $invites = $response->json();
        expect($invites)->toHaveCount(1);
        expect($invites[0])->toHaveKeys(['id', 'email', 'status']);
        expect($invites[0])->not->toHaveKey('token');
    });

    it('does not expose token when creating an invite', function () {
        $admin = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($admin);

        $response = $this->postJson(route('open.workspaces.users.add', $workspace), [
            'email' => 'newinvite@example.com',
            'role' => 'user',
        ])->assertSuccessful();

        $data = $response->json();
        if (isset($data['invite'])) {
            expect($data['invite'])->not->toHaveKey('token');
        }
    });

    it('still stores the token in the database', function () {
        $admin = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($admin);
        UserInvite::inviteUser('test@example.com', 'user', $workspace);

        $invite = UserInvite::latest()->first();
        expect($invite->token)->not->toBeNull();
        expect(strlen($invite->token))->toBe(100);
    });
});
