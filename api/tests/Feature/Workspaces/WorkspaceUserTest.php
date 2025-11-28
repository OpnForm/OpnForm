<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserInvitationEmail;

beforeEach(function () {
    $this->user = $this->actingAsProUser();
    $this->workspace = Workspace::factory()->create();
    $this->workspace->users()->attach($this->user, ['role' => 'admin']);
});

it('can list users in a workspace', function () {

    $this->getJson(route('open.workspaces.users.index', ['workspace' => $this->workspace]))
        ->assertSuccessful()
        ->assertJsonCount(1);
});

it('can add a user to a workspace', function () {
    $newUser = User::factory()->create(['email' => 'newuser@example.com']);
    $this->postJson(route('open.workspaces.users.add', ['workspace' => $this->workspace]), [
        'email' => $newUser->email,
        'role' => 'user',
    ])
        ->assertSuccessful()
        ->assertJson([
            'message' => 'User has been successfully added to workspace.'
        ]);

    expect($this->workspace->users()->count())->toBe(2);
});

it('can send an invitation email to a non-existing user', function () {
    Mail::fake();

    $this->postJson(route('open.workspaces.users.add', ['workspace' => $this->workspace]), [
        'email' => 'nonexisting@example.com',
        'role' => 'user',
    ])
        ->assertSuccessful()
        ->assertJson([
            'message' => 'Registration invitation email sent to user.'
        ]);

    Mail::assertQueued(UserInvitationEmail::class);
});

it('can update user role in a workspace', function () {
    $existingUser = User::factory()->create();
    $this->workspace->users()->attach($existingUser, ['role' => 'user']);

    $this->putJson(route('open.workspaces.users.update-role', [
        'workspace' => $this->workspace,
        'user' => $existingUser
    ]), [
        'role' => 'admin',
    ])
        ->assertSuccessful()
        ->assertJson([
            'message' => 'User role changed successfully.'
        ]);
});

it('can remove a user from a workspace', function () {
    $existingUser = User::factory()->create();
    $this->workspace->users()->attach($existingUser);

    $this->deleteJson(route('open.workspaces.users.remove', [
        'workspace' => $this->workspace,
        'user' => $existingUser
    ]))
        ->assertSuccessful()
        ->assertJson([
            'message' => 'User removed from workspace successfully.'
        ]);

    expect($this->workspace->users()->count())->toBe(1);
});

it('can leave a workspace', function () {
    $this->postJson(route('open.workspaces.leave', ['workspace' => $this->workspace]))
        ->assertSuccessful()
        ->assertJson([
            'message' => 'You have left the workspace successfully.'
        ]);

    expect($this->workspace->users()->count())->toBe(0);
});
