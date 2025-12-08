<?php

use App\Enums\SettingsKey;
use App\Models\Setting;
use App\Models\User;
use App\Rules\ValidReCaptcha;
use App\Service\Telemetry\TelemetryEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

describe('UserCreated event during setup', function () {
    beforeEach(function () {
        Queue::fake();
        Config::set('telemetry.enabled', true);
        Config::set('app.self_hosted', true);
        Config::set('services.re_captcha.secret_key', 'test-secret');

        // Set production environment (telemetry requires both production AND self-hosted)
        app()->detectEnvironment(fn () => 'production');

        Http::fake([
            ValidReCaptcha::RECAPTCHA_VERIFY_URL => Http::response(['success' => true])
        ]);
    });

    it('fires UserCreated event when first user is created during setup', function () {
        // Initialize instance first (simulating setup flow)
        $instanceId = (string) Str::uuid();
        Setting::set(SettingsKey::INSTANCE_ID, $instanceId);

        // Ensure no users exist (setup scenario)
        expect(User::count())->toBe(0);

        // Create first user via RegisterController (simulating setup flow)
        $response = $this->postJson('/register', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'hear_about_us' => 'google',
            'password' => 'Abcd@1234',
            'password_confirmation' => 'Abcd@1234',
            'g-recaptcha-response' => 'test-token',
        ]);

        $response->assertSuccessful();

        // Assert user was created
        $user = User::where('email', 'admin@example.com')->first();
        expect($user)->not->toBeNull();

        // UserCreated event is automatically fired by Eloquent when User is created
        // We verify it by checking that the telemetry job was dispatched

        // Assert telemetry job was dispatched with USER_CREATED event
        Queue::assertPushed(\App\Service\Telemetry\SendTelemetryJob::class, function ($job) {
            return $job->eventName === TelemetryEvent::USER_CREATED->value();
        });
    });

    it('sends telemetry with profileId when instance is initialized', function () {
        // Initialize instance first
        $instanceId = (string) Str::uuid();
        Setting::set(SettingsKey::INSTANCE_ID, $instanceId);

        // Create first user
        $this->postJson('/register', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'hear_about_us' => 'google',
            'password' => 'Abcd@1234',
            'password_confirmation' => 'Abcd@1234',
            'g-recaptcha-response' => 'test-token',
        ])->assertSuccessful();

        // Verify telemetry job was dispatched
        Queue::assertPushed(\App\Service\Telemetry\SendTelemetryJob::class);
    });

    it('dispatches telemetry job even when instance is not initialized', function () {
        // Don't initialize instance
        expect(Setting::get(SettingsKey::INSTANCE_ID))->toBeNull();

        // Create first user
        $this->postJson('/register', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'hear_about_us' => 'google',
            'password' => 'Abcd@1234',
            'password_confirmation' => 'Abcd@1234',
            'g-recaptcha-response' => 'test-token',
        ])->assertSuccessful();

        // UserCreated event is automatically fired by Eloquent when User is created

        // Telemetry job is still dispatched, but will check for instance_id
        // and return early in the job handler if not found
        Queue::assertPushed(\App\Service\Telemetry\SendTelemetryJob::class, function ($job) {
            return $job->eventName === TelemetryEvent::USER_CREATED->value();
        });
    });
});
