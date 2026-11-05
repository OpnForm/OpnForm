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
        Config::set('app.front_url', null);
        Config::set('app.url', 'http://localhost');
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
            'instance_hostname' => null,
        ]);
    });

    it('adds a true paid license flag to instance properties with an active self-hosted license', function () {
        Config::set('app.self_hosted', true);
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_telemetrypaid12345',
            'status' => 'active',
        ]);
        \Illuminate\Support\Facades\Cache::put('self_hosted_license_check', new \App\Service\License\LicenseCheckResult(status: 'active', lastChecked: now()), 3600);

        expect($this->service->getInstanceProperties(['forms_count' => 10]))->toBe([
            'forms_count' => 10,
            'has_paid_license' => true,
            'instance_hostname' => null,
        ]);
    });

    it('does not allow callers to override the paid license flag', function () {
        Config::set('app.self_hosted', true);

        expect($this->service->getInstanceProperties(['has_paid_license' => true]))->toBe([
            'has_paid_license' => false,
            'instance_hostname' => null,
        ]);
    });

    it('includes only the configured hostname in instance properties', function ($frontUrl, $appUrl, $expected) {
        Config::set('app.self_hosted', false);
        Config::set('app.front_url', $frontUrl);
        Config::set('app.url', $appUrl);

        $properties = $this->service->getInstanceProperties(['instance_hostname' => 'untrusted.example.com']);

        expect($properties['instance_hostname'])->toBe($expected);
    })->with([
        'frontend preferred over API' => ['https://forms.example.com', 'https://api.example.com', 'forms.example.com'],
        'credentials, port, path, query and fragment excluded' => ['https://user:password@FORMS.Example.com:8443/private?token=secret#section', null, 'forms.example.com'],
        'trailing dot normalized' => ['https://forms.example.com./', null, 'forms.example.com'],
        'missing frontend falls back to app' => [null, 'https://api.example.com', 'api.example.com'],
        'relative frontend falls back to app' => ['/', 'https://forms.example.com', 'forms.example.com'],
        'malformed frontend falls back to app' => ['https://forms.example.com:invalid', 'https://forms.example.com', 'forms.example.com'],
        'non-HTTP scheme falls back to app' => ['ftp://files.example.com', 'https://forms.example.com', 'forms.example.com'],
        'missing URLs' => [null, null, null],
        'localhost' => ['http://localhost:3000', null, null],
        'localhost subdomain' => ['http://forms.localhost', null, null],
        'container hostname' => ['http://opnform-api', null, null],
        'local domain' => ['http://forms.local', null, null],
        'internal domain' => ['https://forms.internal', null, null],
        'test domain' => ['https://forms.example.test', null, null],
        'home network' => ['http://forms.home.arpa', null, null],
        'private IPv4' => ['http://192.168.1.10', null, null],
        'public IPv4' => ['https://8.8.8.8', null, null],
        'IPv6' => ['http://[2001:db8::1]:8080', null, null],
    ]);
});
