<?php

use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Enterprise\Oidc\Policies\IdentityConnectionPolicy;
use App\Models\User;
use App\Models\Workspace;

uses()->group('oidc', 'feature');

describe('IdentityConnectionPolicy', function () {
    beforeEach(function () {
        // Set up admin email config
        config(['opnform.admin_emails' => ['admin@test.com']]);
        // Set self-hosted mode to bypass Pro checks in tests
        config(['app.self_hosted' => true]);
    });

    it('allows workspace admin to view workspace connections', function () {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);
        $policy = new IdentityConnectionPolicy();

        expect($policy->viewAny($user, $workspace))->toBeTrue();
    });

    it('prevents non-admin from viewing workspace connections', function () {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'member']);
        $policy = new IdentityConnectionPolicy();

        expect($policy->viewAny($user, $workspace))->toBeFalse();
    });

    it('allows admin to view global connections', function () {
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $policy = new IdentityConnectionPolicy();

        expect($policy->viewAny($admin, null))->toBeTrue();
    });

    it('prevents non-admin from viewing global connections', function () {
        $user = User::factory()->create(['email' => 'user@test.com']);
        $policy = new IdentityConnectionPolicy();

        expect($policy->viewAny($user, null))->toBeFalse();
    });

    it('allows admin to view global connection', function () {
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $connection = IdentityConnection::factory()->create(['workspace_id' => null]);
        $policy = new IdentityConnectionPolicy();

        expect($policy->view($admin, $connection))->toBeTrue();
    });

    it('prevents non-admin from viewing global connection', function () {
        $user = User::factory()->create(['email' => 'user@test.com']);
        $connection = IdentityConnection::factory()->create(['workspace_id' => null]);
        $policy = new IdentityConnectionPolicy();

        expect($policy->view($user, $connection))->toBeFalse();
    });

    it('allows workspace admin to view workspace connection', function () {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $connection = IdentityConnection::factory()->create(['workspace_id' => $workspace->id]);
        $policy = new IdentityConnectionPolicy();

        expect($policy->view($user, $connection))->toBeTrue();
    });

    it('prevents non-admin workspace member from viewing workspace connection', function () {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'member']);

        $connection = IdentityConnection::factory()->create(['workspace_id' => $workspace->id]);
        $policy = new IdentityConnectionPolicy();

        expect($policy->view($user, $connection))->toBeFalse();
    });

    it('allows admin to create global connection', function () {
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $policy = new IdentityConnectionPolicy();

        expect($policy->create($admin, null))->toBeTrue();
    });

    it('prevents non-admin from creating global connection', function () {
        $user = User::factory()->create(['email' => 'user@test.com']);
        $policy = new IdentityConnectionPolicy();

        expect($policy->create($user, null))->toBeFalse();
    });

    it('allows workspace admin to create workspace connection', function () {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);
        $policy = new IdentityConnectionPolicy();

        expect($policy->create($user, $workspace))->toBeTrue();
    });

    it('prevents non-admin from creating workspace connection', function () {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'member']);
        $policy = new IdentityConnectionPolicy();

        expect($policy->create($user, $workspace))->toBeFalse();
    });

    it('allows admin to update global connection', function () {
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $connection = IdentityConnection::factory()->create(['workspace_id' => null]);
        $policy = new IdentityConnectionPolicy();

        expect($policy->update($admin, $connection))->toBeTrue();
    });

    it('allows workspace admin to update workspace connection', function () {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $connection = IdentityConnection::factory()->create(['workspace_id' => $workspace->id]);
        $policy = new IdentityConnectionPolicy();

        expect($policy->update($user, $connection))->toBeTrue();
    });

    it('allows admin to delete global connection', function () {
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $connection = IdentityConnection::factory()->create(['workspace_id' => null]);
        $policy = new IdentityConnectionPolicy();

        expect($policy->delete($admin, $connection))->toBeTrue();
    });

    it('allows workspace admin to delete workspace connection', function () {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $user->workspaces()->attach($workspace->id, ['role' => 'admin']);

        $connection = IdentityConnection::factory()->create(['workspace_id' => $workspace->id]);
        $policy = new IdentityConnectionPolicy();

        expect($policy->delete($user, $connection))->toBeTrue();
    });
});
