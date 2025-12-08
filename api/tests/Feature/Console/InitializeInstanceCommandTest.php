<?php

use App\Enums\SettingsKey;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

describe('InitializeInstance Command', function () {
    beforeEach(function () {
        Queue::fake();
        Config::set('telemetry.enabled', true);
        Config::set('app.self_hosted', true); // Enable self-hosted for tests
        // Clean up before each test
        Setting::whereIn('key', [
            SettingsKey::INSTANCE_ID->value,
            SettingsKey::INSTANCE_CREATED_AT->value,
        ])->delete();
    });

    it('creates instance id when it does not exist', function () {
        Artisan::call('telemetry:initialize-instance');

        $instanceId = Setting::get(SettingsKey::INSTANCE_ID);
        expect($instanceId)->not->toBeNull();
        expect($instanceId)->toBeString();
        expect(strlen($instanceId))->toBeGreaterThan(0);
    });

    it('creates instance created at timestamp', function () {
        Artisan::call('telemetry:initialize-instance');

        $createdAt = Setting::get(SettingsKey::INSTANCE_CREATED_AT);
        expect($createdAt)->not->toBeNull();
        expect($createdAt)->toBeString();
        // Should be ISO8601 format
        expect($createdAt)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
    });

    it('does not overwrite existing instance id', function () {
        $existingId = 'existing-instance-id';
        Setting::set(SettingsKey::INSTANCE_ID, $existingId);

        Artisan::call('telemetry:initialize-instance');

        $instanceId = Setting::get(SettingsKey::INSTANCE_ID);
        expect($instanceId)->toBe($existingId);
    });

    it('tracks instance created event on first initialization', function () {
        Artisan::call('telemetry:initialize-instance');

        Queue::assertPushed(\App\Service\Telemetry\SendTelemetryJob::class, function ($job) {
            return $job->eventName === 'instance.created';
        });
    });

    it('does not track instance created event when instance already exists', function () {
        Setting::set(SettingsKey::INSTANCE_ID, 'existing-id');

        Artisan::call('telemetry:initialize-instance');

        Queue::assertNothingPushed();
    });

    it('returns success status', function () {
        $exitCode = Artisan::call('telemetry:initialize-instance');

        expect($exitCode)->toBe(0);
    });

    it('generates valid UUID format', function () {
        Artisan::call('telemetry:initialize-instance');

        $instanceId = Setting::get(SettingsKey::INSTANCE_ID);
        // UUID v4 format: 8-4-4-4-12 hex characters
        expect($instanceId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
    });
});
