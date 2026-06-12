<?php

use App\Models\User;
use App\Rules\ValidReCaptcha;
use Illuminate\Support\Facades\Http;

it('can register', function () {

    Http::fake([
        ValidReCaptcha::RECAPTCHA_VERIFY_URL => Http::response(['success' => true])
    ]);

    $this->postJson('/register', [
        'name' => 'Test User',
        'email' => 'test@test.app',
        'hear_about_us' => 'google',
        'password' => 'Abcd@1234',
        'password_confirmation' => 'Abcd@1234',
        'agree_terms' => true,
        'g-recaptcha-response' => 'test-token', // Mock token for testing
    ])
        ->assertSuccessful()
        ->assertJsonStructure([
            'token',
            'token_type',
            'expires_in',
            'appsumo_license',
            'new_user',
            'user' => [
                'name',
                'email'
            ]
        ]);

    $user = User::where('email', 'test@test.app')->first();
    expect($user)->not->toBeNull();
    expect($user->meta)->toHaveKey('registration_ip');
    expect($user->meta['registration_ip'])->toBe(request()->ip());
});

it('cannot register with existing email', function () {
    User::factory()->create(['email' => 'test@test.app']);

    $this->postJson('/register', [
        'name' => 'Test User',
        'email' => 'test@test.app',
        'password' => 'Abcd@1234',
        'password_confirmation' => 'Abcd@1234',
        'g-recaptcha-response' => 'test-token',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('blocks registering a third user on a free self-hosted instance', function () {
    config(['app.self_hosted' => true]);
    config(['cashier.key' => null]);

    User::factory()->create(['email' => 'owner@example.com']);
    User::factory()->create(['email' => 'member@example.com']);

    $this->postJson('/register', [
        'name' => 'Third User',
        'email' => 'third@example.com',
        'hear_about_us' => 'google',
        'password' => 'Abcd@1234',
        'password_confirmation' => 'Abcd@1234',
        'g-recaptcha-response' => 'test-token',
    ])
        ->assertStatus(403)
        ->assertJson([
            'message' => 'Enterprise license is required to add more than 2 users to a self-hosted instance.',
        ]);

    expect(User::where('email', 'third@example.com')->exists())->toBeFalse();
});

it('cannot register with disposable email', function () {
    Http::fake([
        ValidReCaptcha::RECAPTCHA_VERIFY_URL => Http::response(['success' => true])
    ]);

    // Select random email
    $email = [
        'dumliyupse@gufum.com',
        'kcs79722@zslsz.com',
        'pfizexwxtdifxupdhr@tpwlb.com',
        'qvj86ypqfm@email.edu.pl',
    ][rand(0, 3)];

    $this->postJson('/register', [
        'name' => 'Test disposable',
        'email' => $email,
        'hear_about_us' => 'google',
        'password' => 'Abcd@1234',
        'password_confirmation' => 'Abcd@1234',
        'agree_terms' => true,
        'g-recaptcha-response' => 'test-token',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email'])
        ->assertJson([
            'message' => 'Disposable email addresses are not allowed.',
            'errors' => [
                'email' => [
                    'Disposable email addresses are not allowed.',
                ],
            ],
        ]);
});

it('requires hcaptcha token in production', function () {
    config(['services.re_captcha.secret_key' => 'test-key']);

    Http::fake([
        ValidReCaptcha::RECAPTCHA_VERIFY_URL => Http::response(['success' => true])
    ]);

    $this->postJson('/register', [
        'name' => 'Test User',
        'email' => 'test@test.app',
        'hear_about_us' => 'google',
        'password' => 'Abcd@1234',
        'password_confirmation' => 'Abcd@1234',
        'agree_terms' => true,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['g-recaptcha-response']);
});
