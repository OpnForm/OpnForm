<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Tymon\JWTAuth\Facades\JWTAuth;

it('keeps a pre-migration JWT without a version claim usable until credentials rotate', function () {
    config(['app.jwt_skip_ip_ua_validation' => true]);
    $user = User::factory()->create();
    expect($user->fresh()->auth_version)->toBeNull();
    $claims = JWTAuth::setToken(JWTAuth::fromUser($user))->getPayload()->toArray();
    unset($claims['auth_version']);
    $legacyJwt = app('tymon.jwt.provider.jwt')->encode($claims);

    $this->app['auth']->forgetGuards();
    $this->withToken($legacyJwt)->getJson('/user')->assertOk()->assertJsonPath('id', $user->id);
    $this->patchJson('/settings/password', [
        'current_password' => 'Abcd@1234',
        'password' => 'Changed@12345',
        'password_confirmation' => 'Changed@12345',
    ])->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->withToken($legacyJwt)->getJson('/user')->assertUnauthorized();
    $this->withoutHeader('Authorization');
    $this->app['tymon.jwt']->unsetToken();
    $this->app['auth']->forgetGuards();
    $login = $this->postJson('/login', ['email' => $user->email, 'password' => 'Changed@12345'])->assertOk();
    $this->app['auth']->forgetGuards();
    $this->withToken($login->json('token'))->getJson('/user')->assertOk();
});

it('preserves active credentials and reset links after a rejected password edit', function (string $failure) {
    config(['app.jwt_skip_ip_ua_validation' => true]);
    $user = $this->actingAsUser();
    $jwt = JWTAuth::fromUser($user);
    $personalToken = $user->createToken('integration')->plainTextToken;
    $resetToken = Password::broker()->createToken($user);
    $hash = $user->password;
    $version = $user->auth_version;

    $this->patchJson('/settings/password', [
        'current_password' => $failure === 'wrong current password' ? 'Wrong@12345' : 'Abcd@1234',
        'password' => 'Changed@12345',
        'password_confirmation' => $failure === 'mismatched confirmation' ? 'Different@12345' : 'Changed@12345',
    ])->assertUnprocessable();

    expect($user->fresh()->password)->toBe($hash)
        ->and($user->fresh()->auth_version)->toBe($version)
        ->and(PersonalAccessToken::findToken($personalToken))->not->toBeNull()
        ->and(Password::broker()->tokenExists($user->fresh(), $resetToken))->toBeTrue();
    $this->app['auth']->forgetGuards();
    $this->withToken($jwt)->getJson('/user')->assertOk();
})->with(['wrong current password', 'mismatched confirmation']);

it('keeps active credentials and reset links on ordinary name and email-case edits', function () {
    config(['app.jwt_skip_ip_ua_validation' => true]);
    $user = $this->actingAsUser();
    $jwt = JWTAuth::fromUser($user);
    $personalToken = $user->createToken('integration')->plainTextToken;
    $resetToken = Password::broker()->createToken($user);
    $version = $user->auth_version;

    $this->patchJson('/settings/profile', [
        'name' => 'Élodie O’Connor 李明',
        'email' => strtoupper($user->email),
    ])->assertOk();

    expect($user->fresh()->name)->toBe('Élodie O’Connor 李明')
        ->and($user->fresh()->auth_version)->toBe($version)
        ->and(PersonalAccessToken::findToken($personalToken))->not->toBeNull()
        ->and(Password::broker()->tokenExists($user->fresh(), $resetToken))->toBeTrue();
    $this->app['auth']->forgetGuards();
    $this->withToken($jwt)->getJson('/user')->assertOk();
});

it('rolls back the password and every credential when revocation storage fails', function (string $operation) {
    config(['app.jwt_skip_ip_ua_validation' => true]);
    $user = User::factory()->create();
    $jwt = JWTAuth::fromUser($user);
    $personalToken = $user->createToken('integration')->plainTextToken;
    $resetToken = Password::broker()->createToken($user);
    $hash = $user->password;
    $version = $user->auth_version;
    DB::table('oauth_access_tokens')->insert([
        'id' => 'existing-access', 'user_id' => $user->id, 'client_id' => 1, 'revoked' => false,
    ]);
    DB::table('oauth_refresh_tokens')->insert([
        'id' => 'existing-refresh', 'access_token_id' => 'existing-access', 'revoked' => false,
    ]);
    $rememberToken = $user->remember_token;
    // Fail after the personal-token deletion and refresh-token revocation have run.
    DB::unprepared("CREATE TRIGGER reject_access_revocation BEFORE UPDATE ON oauth_access_tokens BEGIN SELECT RAISE(ABORT, 'revocation storage unavailable'); END");

    try {
        if ($operation === 'email reset') {
            $this->postJson('/password/reset', [
                'email' => $user->email, 'token' => $resetToken,
                'password' => 'Changed@12345', 'password_confirmation' => 'Changed@12345',
            ])->assertStatus(500);
        } else {
            $this->withToken($jwt)->patchJson('/settings/password', [
                'current_password' => 'Abcd@1234',
                'password' => 'Changed@12345', 'password_confirmation' => 'Changed@12345',
            ])->assertStatus(500);
        }
    } finally {
        DB::unprepared('DROP TRIGGER reject_access_revocation');
    }

    expect($user->fresh()->password)->toBe($hash)
        ->and($user->fresh()->auth_version)->toBe($version)
        ->and($user->fresh()->remember_token)->toBe($rememberToken)
        ->and(PersonalAccessToken::findToken($personalToken))->not->toBeNull()
        ->and(Password::broker()->tokenExists($user->fresh(), $resetToken))->toBeTrue();
    $this->assertDatabaseHas('oauth_access_tokens', ['id' => 'existing-access', 'revoked' => false]);
    $this->assertDatabaseHas('oauth_refresh_tokens', ['id' => 'existing-refresh', 'revoked' => false]);
    $this->app['auth']->forgetGuards();
    $this->withToken($jwt)->getJson('/user')->assertOk();
    expect(Hash::check('Abcd@1234', $user->fresh()->password))->toBeTrue();
})->with(['email reset', 'authenticated change']);

it('keeps existing two-factor enrollment usable after email reset without accepting its old login challenge', function () {
    config(['app.jwt_skip_ip_ua_validation' => true]);
    $user = User::factory()->create();
    $secret = $user->createTwoFactorAuth();
    $user->confirmTwoFactorAuth($secret->makeCode());
    $recovery = $user->getRecoveryCodes()->first();
    $code = is_string($recovery) ? $recovery : $recovery['code'];
    $originalSecret = $user->twoFactorAuth->getRawOriginal('shared_secret');
    $pending = $this->postJson('/login', ['email' => $user->email, 'password' => 'Abcd@1234'])
        ->assertUnprocessable()->assertJsonPath('requires_2fa', true)->json('pending_auth_token');
    $resetToken = Password::broker()->createToken($user);

    $this->postJson('/password/reset', [
        'email' => $user->email, 'token' => $resetToken,
        'password' => 'Changed@12345', 'password_confirmation' => 'Changed@12345',
    ])->assertOk();
    $this->app['tymon.jwt']->unsetToken();
    $this->app['auth']->forgetGuards();
    $this->postJson(route('two-factor.verify'), ['pending_auth_token' => $pending, 'code' => $code])
        ->assertUnprocessable()->assertJsonValidationErrors('pending_auth_token');

    $freshPending = $this->postJson('/login', ['email' => $user->email, 'password' => 'Changed@12345'])
        ->assertUnprocessable()->assertJsonPath('requires_2fa', true)->json('pending_auth_token');
    $verified = $this->postJson(route('two-factor.verify'), ['pending_auth_token' => $freshPending, 'code' => $code])
        ->assertOk()->assertJsonPath('user.id', $user->id);
    $this->app['auth']->forgetGuards();
    $this->withToken($verified->json('token'))->getJson('/user')->assertOk();
    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and($user->fresh()->twoFactorAuth->getRawOriginal('shared_secret'))->toBe($originalSecret);
});
