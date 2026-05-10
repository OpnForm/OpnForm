<?php

use App\Models\License;
use App\Service\License\LicenseCheckResult;
use App\Service\License\LicenseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['app.self_hosted' => true]);
    config(['services.license.endpoint' => 'https://api.opnform.com']);
    Cache::flush();
});

describe('checkLicense', function () {
    it('returns invalid when no license key stored', function () {
        $service = app(LicenseService::class);
        $result = $service->checkLicense();

        expect($result->status)->toBe('invalid');
        expect($result->isActive())->toBeFalse();
    });

    it('returns cached result when available', function () {
        $cached = new LicenseCheckResult(
            status: 'active',
            features: ['sso' => true],
            lastChecked: now(),
            expiresAt: now()->addYear(),
        );
        Cache::put('self_hosted_license_check', $cached, 86400);

        License::create([
            'license_key' => 'lic_test123456',
            'license_provider' => 'self_hosted_enterprise',
            'status' => 'active',
            'meta' => [],
        ]);

        $service = app(LicenseService::class);
        $result = $service->checkLicense();

        expect($result->status)->toBe('active');
        expect($result->features)->toBe(['sso' => true]);
    });

    it('validates against API when cache is empty', function () {
        Http::fake([
            'api.opnform.com/licenses/validate' => Http::response([
                'valid' => true,
                'status' => 'active',
                'features' => ['sso' => true, 'multiOrg' => true],
                'expiresAt' => '2027-03-03T23:59:59Z',
            ]),
        ]);

        License::create([
            'license_key' => 'lic_testkey12345',
            'license_provider' => 'self_hosted_enterprise',
            'status' => 'active',
            'meta' => [],
        ]);

        $service = app(LicenseService::class);
        $result = $service->checkLicense();

        expect($result->status)->toBe('active');
        expect($result->features)->toHaveKey('sso');
        expect($result->features['sso'])->toBeTrue();
        Http::assertSent(fn ($req) => str_contains($req->url(), '/licenses/validate'));
    });
});

describe('storeLicenseKey', function () {
    it('stores key only when API validates it as active', function () {
        Http::fake([
            'api.opnform.com/licenses/validate' => Http::response([
                'valid' => true,
                'status' => 'active',
                'features' => ['sso' => true],
                'expiresAt' => '2027-03-03T23:59:59Z',
            ]),
        ]);

        $service = app(LicenseService::class);
        $result = $service->storeLicenseKey('lic_validkey12345');

        expect($result->isActive())->toBeTrue();
        expect($result->status)->toBe('active');
        expect(License::where('license_provider', 'self_hosted_enterprise')->exists())->toBeTrue();
    });

    it('does not store key when API returns invalid', function () {
        Http::fake([
            'api.opnform.com/licenses/validate' => Http::response([
                'valid' => false,
                'status' => 'expired',
                'features' => null,
            ]),
        ]);

        $service = app(LicenseService::class);
        $result = $service->storeLicenseKey('lic_invalidkey12345');

        expect($result->isActive())->toBeFalse();
        expect($result->status)->toBe('expired');
        expect(License::where('license_provider', 'self_hosted_enterprise')->exists())->toBeFalse();
    });

    it('does not store key when API is unreachable', function () {
        Http::fake([
            'api.opnform.com/licenses/validate' => Http::response([], 500),
        ]);

        $service = app(LicenseService::class);
        $result = $service->storeLicenseKey('lic_somekey12345');

        expect($result->isActive())->toBeFalse();
        expect(License::where('license_provider', 'self_hosted_enterprise')->exists())->toBeFalse();
    });
});

describe('grace period', function () {
    it('returns grace status when API fails within 24h of last check', function () {
        Http::fake([
            'api.opnform.com/licenses/validate' => Http::response([], 500),
        ]);

        License::create([
            'license_key' => 'lic_gracekey12345',
            'license_provider' => 'self_hosted_enterprise',
            'status' => 'active',
            'meta' => [],
            'features' => ['sso' => true],
            'last_checked_at' => now()->subHours(12),
            'expires_at' => now()->addYear(),
        ]);

        $service = app(LicenseService::class);
        $result = $service->checkLicense();

        expect($result->status)->toBe('grace');
        expect($result->isActive())->toBeTrue();
        expect($result->features)->toHaveKey('sso');
    });

    it('returns expired when API fails beyond 24h grace period', function () {
        Http::fake([
            'api.opnform.com/licenses/validate' => Http::response([], 500),
        ]);

        License::create([
            'license_key' => 'lic_expiredgrace12345',
            'license_provider' => 'self_hosted_enterprise',
            'status' => 'active',
            'meta' => [],
            'features' => ['sso' => true],
            'last_checked_at' => now()->subHours(25),
            'expires_at' => now()->addYear(),
        ]);

        $service = app(LicenseService::class);
        $result = $service->checkLicense();

        expect($result->status)->toBe('expired');
        expect($result->isActive())->toBeFalse();
        expect($result->features)->toBeNull();
    });

    it('returns invalid when API fails and no last_checked_at exists', function () {
        Http::fake([
            'api.opnform.com/licenses/validate' => Http::response([], 500),
        ]);

        License::create([
            'license_key' => 'lic_nevervalidated12345',
            'license_provider' => 'self_hosted_enterprise',
            'status' => 'active',
            'meta' => [],
            'last_checked_at' => null,
        ]);

        $service = app(LicenseService::class);
        $result = $service->checkLicense();

        expect($result->status)->toBe('invalid');
        expect($result->isActive())->toBeFalse();
    });
});

describe('hasFeature', function () {
    it('returns true for licensed feature when license is active', function () {
        Cache::put('self_hosted_license_check', new LicenseCheckResult(
            status: 'active',
            features: ['sso' => true, 'multiOrg' => true, 'whitelabel' => true],
            lastChecked: now(),
        ), 86400);

        License::create([
            'license_key' => 'lic_features12345',
            'license_provider' => 'self_hosted_enterprise',
            'status' => 'active',
            'meta' => [],
        ]);

        $service = app(LicenseService::class);

        expect($service->hasFeature('sso'))->toBeTrue();
        expect($service->hasFeature('multiOrg'))->toBeTrue();
        expect($service->hasFeature('whitelabel'))->toBeTrue();
        expect($service->hasFeature('nonexistent'))->toBeFalse();
    });

    it('returns false when license is not active', function () {
        $service = app(LicenseService::class);

        expect($service->hasFeature('sso'))->toBeFalse();
    });
});

describe('hasAppFeature', function () {
    it('maps license features to app features correctly', function () {
        Cache::put('self_hosted_license_check', new LicenseCheckResult(
            status: 'active',
            features: ['sso' => true, 'custom_smtp' => true],
            lastChecked: now(),
        ), 86400);

        License::create([
            'license_key' => 'lic_appfeat12345',
            'license_provider' => 'self_hosted_enterprise',
            'status' => 'active',
            'meta' => [],
        ]);

        $service = app(LicenseService::class);

        expect($service->hasAppFeature('sso.oidc'))->toBeTrue();
        expect($service->hasAppFeature('sso.saml'))->toBeTrue();
        expect($service->hasAppFeature('custom_smtp'))->toBeTrue();
        expect($service->hasAppFeature('audit_logs'))->toBeFalse();
    });
});
