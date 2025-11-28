<?php

use App\Models\UserInvite;
use Carbon\Carbon;
use App\Rules\ValidHCaptcha;
use App\Rules\ValidReCaptcha;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = $this->actingAsProUser();
    $this->workspace = $this->createUserWorkspace($this->user);
    Http::fake([
        ValidHCaptcha::H_CAPTCHA_VERIFY_URL => Http::response(['success' => true]),
        ValidReCaptcha::RECAPTCHA_VERIFY_URL => Http::response(['success' => true])
    ]);
});


it('can register with invite token', function () {
    $email = 'invitee@gmail.com';
    $inviteData = ['email' => $email, 'role' => 'user'];
    $this->postJson(route('open.workspaces.users.add', ['workspace' => $this->workspace]), $inviteData)
        ->assertSuccessful();

    expect($this->workspace->invites()->count())->toBe(1);
    $userInvite = UserInvite::latest()->first();
    $token = $userInvite->token;

    $this->postJson('/logout')
        ->assertSuccessful();

    // Register with token
    $response = $this->postJson('/register', [
        'name' => 'Invitee',
        'email' => $email,
        'hear_about_us' => 'google',
        'password' => 'Abcd@1234',
        'password_confirmation' => 'Abcd@1234',
        'agree_terms' => true,
        'invite_token' => $token,
        'h-captcha-response' => 'test-token',
        'g-recaptcha-response' => 'test-token',
    ]);
    $response->assertSuccessful();
    expect($this->workspace->users()->count())->toBe(2);
});

it('cannot register with expired invite token', function () {
    $email = 'invitee@gmail.com';
    $inviteData = ['email' => $email, 'role' => 'user'];
    $this->postJson(route('open.workspaces.users.add', ['workspace' => $this->workspace]), $inviteData)
        ->assertSuccessful();

    expect($this->workspace->invites()->count())->toBe(1);
    $userInvite = UserInvite::latest()->first();
    $token = $userInvite->token;

    $this->postJson('/logout')
        ->assertSuccessful();

    Carbon::setTestNow(now()->addDays(8));
    // Register with token
    $response = $this->postJson('/register', [
        'name' => 'Invitee',
        'email' => $email,
        'hear_about_us' => 'google',
        'password' => 'Abcd@1234',
        'password_confirmation' => 'Abcd@1234',
        'agree_terms' => true,
        'invite_token' => $token,
        'h-captcha-response' => 'test-token',
        'g-recaptcha-response' => 'test-token',
    ]);
    $response->assertStatus(400)->assertJson([
        'message' => 'Invite token has expired.',
    ]);
    expect($this->workspace->users()->count())->toBe(1);
});

it('cannot re-register with accepted invite token', function () {
    $email = 'invitee@gmail.com';
    $inviteData = ['email' => $email, 'role' => 'user'];
    $this->postJson(route('open.workspaces.users.add', ['workspace' => $this->workspace]), $inviteData)
        ->assertSuccessful();

    expect($this->workspace->invites()->count())->toBe(1);
    $userInvite = UserInvite::latest()->first();
    $token = $userInvite->token;

    $this->postJson('/logout')
        ->assertSuccessful();

    // Register with token
    $response = $this->postJson('/register', [
        'name' => 'Invitee',
        'email' => $email,
        'hear_about_us' => 'google',
        'password' => 'Abcd@1234',
        'password_confirmation' => 'Abcd@1234',
        'agree_terms' => true,
        'invite_token' => $token,
        'h-captcha-response' => 'test-token',
        'g-recaptcha-response' => 'test-token',
    ]);
    $response->assertSuccessful();
    expect($this->workspace->users()->count())->toBe(2);

    $this->postJson('/logout')
        ->assertSuccessful();

    // Register again with same used token
    $response = $this->postJson('/register', [
        'name' => 'Invitee',
        'email' => $email,
        'hear_about_us' => 'google',
        'password' => 'Abcd@1234',
        'password_confirmation' => 'Abcd@1234',
        'agree_terms' => true,
        'invite_token' => $token,
        'h-captcha-response' => 'test-token',
        'g-recaptcha-response' => 'test-token',
    ]);

    $response->assertStatus(422)->assertJson([
        'message' => 'The email has already been taken.',
    ]);
    expect($this->workspace->users()->count())->toBe(2);
});

it('can cancel user invite', function () {
    $email = 'invitee@gmail.com';
    $inviteData = ['email' => $email, 'role' => 'user'];
    $response = $this->postJson(route('open.workspaces.users.add', ['workspace' => $this->workspace]), $inviteData)
        ->assertSuccessful();

    expect($this->workspace->invites()->count())->toBe(1);
    $userInvite = UserInvite::latest()->first();
    $token = $userInvite->token;

    // Cancel the invite
    $this->deleteJson(route('open.workspaces.invites.cancel', ['workspace' => $this->workspace, 'inviteId' => $userInvite->id]))
        ->assertSuccessful();

    $this->postJson('/logout')
        ->assertSuccessful();

    // Register with token
    $response = $this->postJson('/register', [
        'name' => 'Invitee',
        'email' => $email,
        'hear_about_us' => 'google',
        'password' => 'Abcd@1234',
        'password_confirmation' => 'Abcd@1234',
        'agree_terms' => true,
        'invite_token' => $token,
        'h-captcha-response' => 'test-token',
        'g-recaptcha-response' => 'test-token',
    ]);
    $response->assertStatus(400)->assertJson([
        'message' => 'Invite token is invalid.',
    ]);

    expect($this->workspace->users()->count())->toBe(1);
});
