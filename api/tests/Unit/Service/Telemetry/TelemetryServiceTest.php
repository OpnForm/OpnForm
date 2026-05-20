<?php

use App\Enums\SettingsKey;
use App\Models\Setting;
use App\Service\Telemetry\TelemetryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

uses(TestCase::class);

describe('TelemetryService', function () {
    beforeEach(function () {
        Cache::flush();
        $this->service = new TelemetryService();
    });

    it('can be instantiated', function () {
        expect($this->service)->toBeInstanceOf(TelemetryService::class);
    });

    it('returns false when telemetry config is disabled', function () {
        Config::set('telemetry.enabled', false);

        expect($this->service->shouldSendTelemetry())->toBeFalse();
    });

    it('returns false when not in production and not self-hosted', function () {
        Config::set('telemetry.enabled', true);
        Config::set('app.self_hosted', false);
        app()->detectEnvironment(fn () => 'local');

        expect($this->service->shouldSendTelemetry())->toBeFalse();
    });

    it('returns false when only in production environment (needs self-hosted too)', function () {
        Config::set('telemetry.enabled', true);
        Config::set('app.self_hosted', false);
        app()->detectEnvironment(fn () => 'production');

        expect($this->service->shouldSendTelemetry())->toBeFalse();
    });

    it('returns false when only self-hosted mode is enabled (needs production too)', function () {
        Config::set('telemetry.enabled', true);
        Config::set('app.self_hosted', true);
        app()->detectEnvironment(fn () => 'local');

        expect($this->service->shouldSendTelemetry())->toBeFalse();
    });

    it('returns true when both production and self-hosted', function () {
        Config::set('telemetry.enabled', true);
        Config::set('app.self_hosted', true);
        app()->detectEnvironment(fn () => 'production');

        expect($this->service->shouldSendTelemetry())->toBeTrue();
    });

    it('returns instance id when set', function () {
        $instanceId = 'test-instance-id';
        Setting::set(SettingsKey::INSTANCE_ID, $instanceId);

        expect($this->service->getInstanceId())->toBe($instanceId);
    });

    it('returns null when instance id is not set', function () {
        Setting::where('key', SettingsKey::INSTANCE_ID->value)->delete();

        expect($this->service->getInstanceId())->toBeNull();
    });

    it('returns configured endpoint', function () {
        $endpoint = 'https://test-endpoint.com/track';
        Config::set('telemetry.endpoint', $endpoint);

        expect($this->service->getEndpoint())->toBe($endpoint);
    });

    it('returns configured client id', function () {
        $clientId = 'test-client-id';
        Config::set('telemetry.client_id', $clientId);

        expect($this->service->getClientId())->toBe($clientId);
    });

    it('returns configured client secret', function () {
        $clientSecret = 'test-client-secret';
        Config::set('telemetry.client_secret', $clientSecret);

        expect($this->service->getClientSecret())->toBe($clientSecret);
    });

    it('adds a false paid license flag to instance properties without a license', function () {
        Config::set('app.self_hosted', true);

        expect($this->service->getInstanceProperties())->toBe([
            'has_paid_license' => false,
        ]);
    });

    it('adds a true paid license flag to instance properties with an active self-hosted license', function () {
        Config::set('app.self_hosted', true);
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_telemetrypaid12345',
            'status' => 'active',
        ]);

        expect($this->service->getInstanceProperties(['forms_count' => 10]))->toBe([
            'forms_count' => 10,
            'has_paid_license' => true,
        ]);
    });

    it('does not allow callers to override the paid license flag', function () {
        Config::set('app.self_hosted', true);

        expect($this->service->getInstanceProperties(['has_paid_license' => true]))->toBe([
            'has_paid_license' => false,
        ]);
    });
});
