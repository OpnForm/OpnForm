<?php

use App\Jobs\Auth\SendPasswordResetLink;
use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withMiddleware(ThrottleRequests::class);
});

it('returns the same response whether or not the email exists', function () {
    Bus::fake();

    User::factory()->create([
        'email' => 'registered@example.com',
    ]);

    $registeredResponse = $this
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
        ->postJson('/password/email', [
            'email' => 'registered@example.com',
        ]);

    $unregisteredResponse = $this
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
        ->postJson('/password/email', [
            'email' => 'missing@example.com',
        ]);

    $registeredResponse->assertOk();
    $unregisteredResponse->assertOk();
    expect($registeredResponse->json())->toBe($unregisteredResponse->json());
    Bus::assertDispatchedAfterResponseTimes(SendPasswordResetLink::class, 2);
});

it('normalizes the email before dispatching the reset job', function () {
    Bus::fake();

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.11'])
        ->postJson('/password/email', [
            'email' => '  Registered@Example.COM  ',
        ])
        ->assertOk();

    Bus::assertDispatchedAfterResponse(
        SendPasswordResetLink::class,
        fn (SendPasswordResetLink $job) => $job->email === 'registered@example.com'
    );
});

it('rejects a structured email value without dispatching a reset job', function () {
    Bus::fake();

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.12'])
        ->postJson('/password/email', [
            'email' => ['not-a-string'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    Bus::assertNotDispatched(SendPasswordResetLink::class);
});

it('dispatches immediately to an asynchronous queue connection', function () {
    Bus::fake();
    config(['queue.default' => 'redis']);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.13'])
        ->postJson('/password/email', [
            'email' => 'registered@example.com',
        ])
        ->assertOk();

    Bus::assertDispatched(SendPasswordResetLink::class);
    Bus::assertNotDispatchedAfterResponse(SendPasswordResetLink::class);
});

it('sends a reset notification only when the normalized email exists', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'registered@example.com',
    ]);

    (new SendPasswordResetLink('registered@example.com'))->handle();
    (new SendPasswordResetLink('missing@example.com'))->handle();

    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertCount(1);
});

it('limits password reset requests per ip address', function () {
    Bus::fake();

    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->postJson('/password/email', [
                'email' => "missing-{$attempt}@example.com",
            ])
            ->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->postJson('/password/email', [
            'email' => 'missing-6@example.com',
        ])
        ->assertTooManyRequests();
});

it('limits password reset requests per email across ip addresses', function () {
    Bus::fake();

    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$attempt}"])
            ->postJson('/password/email', [
                'email' => 'target@example.com',
            ])
            ->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.100'])
        ->postJson('/password/email', [
            'email' => 'target@example.com',
        ])
        ->assertTooManyRequests();
});
