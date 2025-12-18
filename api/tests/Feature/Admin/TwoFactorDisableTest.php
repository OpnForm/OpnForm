<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Config;

function setupUsersFor2FA()
{
    Config::set('opnform.moderator_emails', ['example@moderator.com']);
    $moderator = User::factory()->create([
        'email' => 'example@moderator.com',
    ]);

    $user = User::factory()->create();

    return [$moderator, $user];
}

function enableTwoFactorFor(User $user): void
{
    $secret = $user->createTwoFactorAuth();
    $code = $secret->makeCode();
    $user->confirmTwoFactorAuth($code);
}

it('can disable 2FA for a user', function () {
    [$moderator, $user] = setupUsersFor2FA();
    enableTwoFactorFor($user);

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();

    $this->actingAs($moderator)
        ->postJson('/moderator/disable-two-factor-authentication', [
            'user_id' => $user->id,
            'reason' => 'User lost access to authenticator app',
        ])
        ->assertSuccessful()
        ->assertJson([
            'message' => 'Two-factor authentication has been disabled successfully.',
        ])
        ->assertJsonPath('user.two_factor_enabled', false);

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('does not allow a non-moderator to disable 2FA', function () {
    $nonModerator = User::factory()->create();
    $user = User::factory()->create();
    enableTwoFactorFor($user);

    $this->actingAs($nonModerator)
        ->postJson('/moderator/disable-two-factor-authentication', [
            'user_id' => $user->id,
            'reason' => 'Attempting to disable 2FA',
        ])
        ->assertForbidden();

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

it('cannot disable 2FA for an admin user', function () {
    [$moderator, $user] = setupUsersFor2FA();
    // Make user an admin by adding their email to admin_emails config
    Config::set('opnform.admin_emails', [$user->email]);
    enableTwoFactorFor($user);

    $this->actingAs($moderator)
        ->postJson('/moderator/disable-two-factor-authentication', [
            'user_id' => $user->id,
            'reason' => 'Attempting to disable admin 2FA',
        ])
        ->assertStatus(400)
        ->assertJson([
            'message' => 'You cannot disable 2FA for an admin.',
        ]);

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

it('returns error when 2FA is not enabled', function () {
    [$moderator, $user] = setupUsersFor2FA();

    expect($user->hasTwoFactorEnabled())->toBeFalse();

    $this->actingAs($moderator)
        ->postJson('/moderator/disable-two-factor-authentication', [
            'user_id' => $user->id,
            'reason' => 'Test reason',
        ])
        ->assertStatus(400)
        ->assertJson([
            'message' => 'Two-factor authentication is not enabled.',
        ]);
});

it('requires reason to disable 2FA', function () {
    [$moderator, $user] = setupUsersFor2FA();
    enableTwoFactorFor($user);

    $this->actingAs($moderator)
        ->postJson('/moderator/disable-two-factor-authentication', [
            'user_id' => $user->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
});

it('requires user_id to disable 2FA', function () {
    [$moderator, $user] = setupUsersFor2FA();

    $this->actingAs($moderator)
        ->postJson('/moderator/disable-two-factor-authentication', [
            'reason' => 'Test reason',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);
});
