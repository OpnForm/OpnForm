<?php

use App\Integrations\OAuth\OAuthProviderService as Provider;
use App\Models\User;
use App\Service\OAuth\OAuthProviderService;

it('creates an OAuth link when the provider supplies no refresh token', function (Provider $provider, array $refresh) {
    $user = User::factory()->create();
    $link = app(OAuthProviderService::class)->createOrUpdateProvider($user, $provider, [
        'provider_user_id' => 'new-provider-account',
        'access_token' => 'new-access-token',
        'name' => 'Example User',
        'email' => $user->email,
        ...$refresh,
    ]);

    expect($link->fresh()->refresh_token)->toBe('')
        ->and($link->fresh()->provider_user_id)->toBe('new-provider-account');
})->with([
    'Google One Tap' => [Provider::GoogleOneTap, ['refresh_token' => null]],
    'Google without a new token' => [Provider::Google, []],
    'Telegram' => [Provider::Telegram, []],
    'empty token' => [Provider::Google, ['refresh_token' => '']],
]);

it('keeps the existing refresh token when a later login supplies none', function (array $refresh) {
    $user = User::factory()->create();
    $service = app(OAuthProviderService::class);
    $data = [
        'provider_user_id' => 'existing-provider-account',
        'access_token' => 'old-access-token',
        'name' => 'Example User',
        'email' => $user->email,
    ];
    $original = $service->createOrUpdateProvider($user, Provider::Google, [
        ...$data, 'refresh_token' => 'existing-refresh-token',
    ]);
    $updated = $service->createOrUpdateProvider($user, Provider::Google, [
        ...$data, 'access_token' => 'new-access-token', ...$refresh,
    ]);

    expect($updated->id)->toBe($original->id)
        ->and($updated->fresh()->refresh_token)->toBe('existing-refresh-token')
        ->and($updated->fresh()->access_token)->toBe('new-access-token');
})->with([
    'null' => [['refresh_token' => null]],
    'empty' => [['refresh_token' => '']],
    'absent' => [[]],
]);

it('replaces the existing refresh token when the provider supplies a new one', function () {
    $user = User::factory()->create();
    $service = app(OAuthProviderService::class);
    $data = [
        'provider_user_id' => 'existing-provider-account',
        'access_token' => 'access-token',
        'name' => 'Example User',
        'email' => $user->email,
    ];
    $original = $service->createOrUpdateProvider($user, Provider::Google, [
        ...$data, 'refresh_token' => 'old-refresh-token',
    ]);
    $updated = $service->createOrUpdateProvider($user, Provider::Google, [
        ...$data, 'refresh_token' => 'new-refresh-token',
    ]);

    expect($updated->id)->toBe($original->id)
        ->and($updated->fresh()->refresh_token)->toBe('new-refresh-token');
});
