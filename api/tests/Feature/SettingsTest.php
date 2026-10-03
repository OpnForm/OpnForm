<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('update profile info', function () {
    $this->actingAs($user = User::factory()->create())
        ->patchJson('/settings/profile', [
            'name' => 'Test User',
            'email' => 'test@test.app',
            'current_password' => 'Abcd@1234',
        ])
        ->assertSuccessful()
        ->assertJsonStructure(['id', 'name', 'email']);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Test User',
        'email' => 'test@test.app',
    ]);
});

it('requires the current password when changing email', function () {
    $user = User::factory()->create([
        'email' => 'original@example.com',
    ]);

    $this->actingAs($user)
        ->patchJson('/settings/profile', [
            'name' => 'Test User',
            'email' => 'changed@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect($user->refresh()->email)->toBe('original@example.com');
});

it('rejects an incorrect current password when changing email', function () {
    $user = User::factory()->create([
        'email' => 'original@example.com',
    ]);

    $this->actingAs($user)
        ->patchJson('/settings/profile', [
            'name' => 'Test User',
            'email' => 'changed@example.com',
            'current_password' => 'incorrect-password',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect($user->refresh()->email)->toBe('original@example.com');
});

it('throttles repeated current password guesses when changing email', function () {
    $user = User::factory()->create([
        'email' => 'original@example.com',
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($user)
            ->patchJson('/settings/profile', [
                'name' => 'Test User',
                'email' => 'changed@example.com',
                'current_password' => "incorrect-password-{$attempt}",
            ])
            ->assertUnprocessable();
    }

    $this->actingAs($user)
        ->patchJson('/settings/profile', [
            'name' => 'Test User',
            'email' => 'changed@example.com',
            'current_password' => 'Abcd@1234',
        ])
        ->assertTooManyRequests();

    expect($user->refresh()->email)->toBe('original@example.com');
});

it('does not require the current password when email is unchanged', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);

    $this->actingAs($user)
        ->patchJson('/settings/profile', [
            'name' => 'Updated Name',
            'email' => 'original@example.com',
        ])
        ->assertSuccessful();

    expect($user->refresh()->name)->toBe('Updated Name');
});

it('rejects a structured email value without throwing a server error', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/settings/profile', [
            'name' => 'Test User',
            'email' => ['not-a-string'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('does not offer an email change to a passwordless account', function () {
    $user = User::factory()->create([
        'email' => 'oauth@example.com',
        'password' => null,
        'meta' => [
            'signup_provider' => 'google',
            'signup_provider_user_id' => 'google-user-id',
        ],
    ]);

    $this->actingAs($user)
        ->getJson('/user')
        ->assertSuccessful()
        ->assertJsonPath('can_change_email', false);

    $this->actingAs($user)
        ->patchJson('/settings/profile', [
            'name' => 'OAuth User',
            'email' => 'changed@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect($user->refresh()->email)->toBe('oauth@example.com');
});

it('does not offer an email change to an OIDC-managed account', function () {
    $user = User::factory()->create([
        'email' => 'oidc@example.com',
        'password' => Hash::make('unavailable-random-password'),
        'meta' => [
            'signup_provider' => 'oidc',
            'signup_provider_user_id' => 'oidc-user-id',
        ],
    ]);

    $this->actingAs($user)
        ->getJson('/user')
        ->assertSuccessful()
        ->assertJsonPath('can_change_email', false);

    $this->actingAs($user)
        ->patchJson('/settings/profile', [
            'name' => 'OIDC User',
            'email' => 'changed@example.com',
            'current_password' => 'unavailable-random-password',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect($user->refresh()->email)->toBe('oidc@example.com');
});

it('offers an email change to an account with a local password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/user')
        ->assertSuccessful()
        ->assertJsonPath('can_change_email', true);
});

it('update password', function () {
    $this->actingAs($user = User::factory()->create())
        ->patchJson('/settings/password', [
            'current_password' => 'Abcd@1234',
            'password' => 'Abcd@1234_updated',
            'password_confirmation' => 'Abcd@1234_updated',
        ])
        ->assertSuccessful();

    $this->assertTrue(Hash::check('Abcd@1234_updated', $user->password));
});
