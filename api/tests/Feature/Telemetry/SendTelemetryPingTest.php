<?php

use App\Enums\SettingsKey;
use App\Models\Setting;
use App\Service\Telemetry\TelemetryEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

describe('SendTelemetryPing command', function () {
    beforeEach(function () {
        Config::set('telemetry.enabled', true);
        Config::set('app.self_hosted', true);
        Config::set('telemetry.endpoint', 'https://test-endpoint.com/track');
        Config::set('telemetry.client_id', 'test-client-id');
        Config::set('telemetry.client_secret', 'test-client-secret');
        app()->detectEnvironment(fn () => 'production');
    });

    afterEach(function () {
        Carbon::setTestNow();
    });

    it('includes paid license flag in ping identification and event properties', function () {
        Carbon::setTestNow(Carbon::parse('2026-01-01 05:00:00'));

        $instanceId = collect(range(1, 1000))
            ->map(fn ($index) => "instance-{$index}")
            ->first(fn ($candidate) => hexdec(substr(md5($candidate), 0, 2)) % 24 === 5);

        Setting::set(SettingsKey::INSTANCE_ID, $instanceId);
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_telemetrypingpaid12345',
            'status' => 'active',
        ]);

        Http::fake([
            'test-endpoint.com/track' => Http::response([], 200),
        ]);

        Artisan::call('telemetry:ping');

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            return $request['type'] === 'identify'
                && $request['payload']['properties']['has_paid_license'] === true
                && $request['payload']['properties']['users_count'] === 0;
        });

        Http::assertSent(function ($request) {
            return $request['type'] === 'track'
                && $request['payload']['name'] === TelemetryEvent::INSTANCE_PING->value()
                && $request['payload']['properties']['has_paid_license'] === true;
        });
    });
});
