<?php

use App\Models\User;
use App\Rules\ValidReCaptcha;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('invalidates outstanding reset links after an authenticated password change', function () {
    $user = $this->actingAsUser();
    $token = Password::broker()->createToken($user);

    $this->patchJson('/settings/password', [
        'current_password' => 'Abcd@1234',
        'password' => 'NewPassword@123',
        'password_confirmation' => 'NewPassword@123',
    ])->assertNoContent();

    expect(Password::broker()->tokenExists($user->fresh(), $token))->toBeFalse();
    $this->app['auth']->forgetGuards();
    $this->postJson('/password/reset', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'AttackerPassword@123',
        'password_confirmation' => 'AttackerPassword@123',
    ])->assertStatus(400);
    expect(Hash::check('NewPassword@123', $user->fresh()->password))->toBeTrue();
});

it('does not invalidate a reset link on a rejected password change', function () {
    $user = $this->actingAsUser();
    $token = Password::broker()->createToken($user);
    $this->patchJson('/settings/password', [
        'current_password' => 'wrong-password',
        'password' => 'NewPassword@123',
        'password_confirmation' => 'NewPassword@123',
    ])->assertUnprocessable();
    expect(Password::broker()->tokenExists($user, $token))->toBeTrue();
});

it('removes reset tokens for the old email when changing email', function () {
    Notification::fake();
    $user = $this->actingAsUser();
    $token = Password::broker()->createToken($user);
    $oldEmail = $user->email;
    $this->patchJson('/settings/profile', [
        'name' => $user->name,
        'email' => 'new@example.com',
        'current_password' => 'Abcd@1234',
    ])->assertSuccessful();

    $oldIdentity = new User(['email' => $oldEmail]);
    expect(Password::broker()->tokenExists($oldIdentity, $token))->toBeFalse();
});

it('rejects oversized passwords at registration before hashing', function (string $password) {
    Http::fake([ValidReCaptcha::RECAPTCHA_VERIFY_URL => Http::response(['success' => true])]);
    $this->postJson('/register', [
        'name' => 'Test User',
        'email' => 'oversized@example.com',
        'hear_about_us' => 'google',
        'agree_terms' => true,
        'g-recaptcha-response' => 'test-token',
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
    expect(User::where('email', 'oversized@example.com')->exists())->toBeFalse();
})->with([str_repeat('A', 73).'1@', str_repeat('é', 36).'A1@', str_repeat('A', 100000).'1@']);

it('rejects oversized passwords when resetting or updating a password', function (string $endpoint) {
    $user = $endpoint === '/settings/password' ? $this->actingAsUser() : User::factory()->create();
    $token = Password::broker()->createToken($user);
    $password = str_repeat('A', 73).'1@';
    $payload = [
        'email' => $user->email,
        'token' => $token,
        'current_password' => 'Abcd@1234',
        'password' => $password,
        'password_confirmation' => $password,
    ];
    $response = $endpoint === '/settings/password'
        ? $this->patchJson($endpoint, $payload)
        : $this->postJson($endpoint, $payload);
    $response->assertUnprocessable()->assertJsonValidationErrors('password');
    expect(Hash::check('Abcd@1234', $user->fresh()->password))->toBeTrue();
})->with(['/settings/password', '/password/reset']);

it('accepts a password at the bcrypt byte limit', function () {
    $this->actingAsUser();
    $password = str_repeat('A', 70).'1@';
    $this->patchJson('/settings/password', [
        'current_password' => 'Abcd@1234',
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertNoContent();
});

it('rejects links and markup in account names at signup', function (string $name) {
    Http::fake([ValidReCaptcha::RECAPTCHA_VERIFY_URL => Http::response(['success' => true])]);
    $this->postJson('/register', [
        'name' => $name,
        'email' => 'name-test@example.com',
        'hear_about_us' => 'google',
        'agree_terms' => true,
        'g-recaptcha-response' => 'test-token',
        'password' => 'Abcd@1234',
        'password_confirmation' => 'Abcd@1234',
    ])->assertUnprocessable()->assertJsonValidationErrors('name');
})->with(['https://evil.example click here', 'evil.example', '<a href="https://evil.example">Support</a>']);

it('rejects links in profile names and keeps international names valid', function () {
    $user = $this->actingAsUser();
    $this->patchJson('/settings/profile', [
        'name' => 'https://evil.example',
        'email' => $user->email,
    ])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->patchJson('/settings/profile', [
        'name' => "Élodie O’Connor 李明",
        'email' => $user->email,
    ])->assertSuccessful();
});
