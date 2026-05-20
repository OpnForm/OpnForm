<?php

use App\Enums\SettingsKey;
use App\Models\Setting;
use App\Service\License\LicenseCheckResult;
use App\Service\License\LicenseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
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

    it('returns cached result when available and a key is installed', function () {
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_test123456',
        ]);

        $cached = new LicenseCheckResult(
            status: 'active',
            features: ['sso' => true],
            lastChecked: now(),
            expiresAt: now()->addYear(),
        );
        Cache::put('self_hosted_license_check', $cached, 86400);

        $service = app(LicenseService::class);
        $result = $service->checkLicense();

        expect($result->status)->toBe('active');
        expect($result->features)->toBe(['sso' => true]);
    });

    it('validates against API when cache is empty', function () {
        Http::fake([
            'https://api.opnform.com/licenses/validate' => Http::response([
                'valid' => true,
                'status' => 'active',
                'features' => ['sso' => true, 'multiOrg' => true],
                'expiresAt' => '2027-03-03T23:59:59Z',
                'licenseId' => '10',
                'activationId' => '20',
            ]),
        ]);

        $this->storeSelfHostedLicense([
            'license_key' => 'lic_testkey12345',
        ]);

        $service = app(LicenseService::class);
        $result = $service->checkLicense();

        expect($result->status)->toBe('active');
        expect($result->features)->toHaveKey('sso');
        expect($result->features['sso'])->toBeTrue();
        expect($result->cloudLicenseId)->toBe('10');
        expect($result->activationId)->toBe('20');

        Http::assertSent(
            fn ($request) =>
            str_contains($request->url(), '/licenses/validate')
            && $request['licenseKey'] === 'lic_testkey12345'
            && is_string($request['instanceId'])
        );
    });
});

describe('storeLicenseKey', function () {
    it('stores key in settings only when API validates it as active', function () {
        Http::fake([
            'https://api.opnform.com/licenses/validate' => Http::response([
                'valid' => true,
                'status' => 'active',
                'features' => ['sso' => true],
                'expiresAt' => '2027-03-03T23:59:59Z',
                'licenseId' => '11',
                'activationId' => '21',
            ]),
        ]);

        $service = app(LicenseService::class);
        $result = $service->storeLicenseKey('lic_validkey12345');
        $stored = Setting::get(SettingsKey::SELF_HOSTED_LICENSE);

        expect($result->isActive())->toBeTrue();
        expect($result->status)->toBe('active');
        expect($stored)->not->toBeNull();
        expect(Crypt::decryptString($stored['license_key']))->toBe('lic_validkey12345');
        expect($stored['cloud_license_id'])->toBe('11');
        expect($stored['activation_id'])->toBe('21');
    });

    it('does not store key when API returns invalid', function () {
        Http::fake([
            'https://api.opnform.com/licenses/validate' => Http::response([
                'valid' => false,
                'status' => 'expired',
                'features' => null,
            ]),
        ]);

        $service = app(LicenseService::class);
        $result = $service->storeLicenseKey('lic_invalidkey12345');

        expect($result->isActive())->toBeFalse();
        expect($result->status)->toBe('expired');
        expect(Setting::where('key', SettingsKey::SELF_HOSTED_LICENSE->value)->exists())->toBeFalse();
    });

    it('does not replace an existing valid key with an invalid candidate', function () {
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_existing_valid_key',
            'features' => ['sso' => true],
        ]);

        Http::fake([
            'https://api.opnform.com/licenses/validate' => Http::response([
                'valid' => false,
                'status' => 'invalid',
                'features' => null,
            ]),
        ]);

        $service = app(LicenseService::class);
        $result = $service->storeLicenseKey('lic_bad_replacement_key');

        expect($result->isActive())->toBeFalse();
        expect($service->getLicenseKey())->toBe('lic_existing_valid_key');
    });

    it('does not replace an existing valid key when the candidate reached its activation limit', function () {
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_existing_valid_key',
            'features' => ['sso' => true],
        ]);

        Http::fake([
            'https://api.opnform.com/licenses/validate' => Http::response([
                'valid' => false,
                'status' => 'activation_limit_reached',
                'features' => null,
            ]),
        ]);

        $service = app(LicenseService::class);
        $result = $service->storeLicenseKey('lic_reused_candidate_key');

        expect($result->isActive())->toBeFalse();
        expect($result->status)->toBe('activation_limit_reached');
        expect($service->getLicenseKey())->toBe('lic_existing_valid_key');
    });

    it('replaces an existing key only after the candidate receives a fresh active response', function () {
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_existing_valid_key',
            'features' => ['sso' => true],
            'cloud_license_id' => 'old-license-id',
            'activation_id' => 'old-activation-id',
        ]);

        Http::fake([
            'https://api.opnform.com/licenses/validate' => Http::response([
                'valid' => true,
                'status' => 'active',
                'features' => ['sso' => true, 'custom_smtp' => true],
                'expiresAt' => '2027-03-03T23:59:59Z',
                'licenseId' => 'new-license-id',
                'activationId' => 'new-activation-id',
            ]),
        ]);

        $service = app(LicenseService::class);
        $result = $service->storeLicenseKey('lic_new_active_candidate');
        $stored = Setting::get(SettingsKey::SELF_HOSTED_LICENSE);

        expect($result->isActive())->toBeTrue();
        expect($service->getLicenseKey())->toBe('lic_new_active_candidate');
        expect(Crypt::decryptString($stored['license_key']))->toBe('lic_new_active_candidate');
        expect($stored['cloud_license_id'])->toBe('new-license-id');
        expect($stored['activation_id'])->toBe('new-activation-id');
        expect($stored['features'])->toBe(['sso' => true, 'custom_smtp' => true]);
    });

    it('does not accept candidate keys from grace fallback when API is unreachable', function () {
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_existing_valid_key',
            'features' => ['sso' => true],
            'last_checked_at' => now()->subHours(1)->format('c'),
        ]);

        Http::fake([
            'https://api.opnform.com/licenses/validate' => Http::response([], 500),
        ]);

        $service = app(LicenseService::class);
        $result = $service->storeLicenseKey('lic_somekey12345');

        expect($result->isActive())->toBeFalse();
        expect($service->getLicenseKey())->toBe('lic_existing_valid_key');
    });
});

describe('grace period', function () {
    it('returns grace status when API fails within 24h of last check', function () {
        Http::fake([
            'https://api.opnform.com/licenses/validate' => Http::response([], 500),
        ]);

        $this->storeSelfHostedLicense([
            'license_key' => 'lic_gracekey12345',
            'features' => ['sso' => true],
            'last_checked_at' => now()->subHours(12)->format('c'),
            'expires_at' => now()->addYear()->format('c'),
        ]);

        $service = app(LicenseService::class);
        $result = $service->checkLicense();

        expect($result->status)->toBe('grace');
        expect($result->isActive())->toBeTrue();
        expect($result->features)->toHaveKey('sso');
    });

    it('returns expired when API fails beyond 24h grace period', function () {
        Http::fake([
            'https://api.opnform.com/licenses/validate' => Http::response([], 500),
        ]);

        $this->storeSelfHostedLicense([
            'license_key' => 'lic_expiredgrace12345',
            'features' => ['sso' => true],
            'last_checked_at' => now()->subHours(25)->format('c'),
            'expires_at' => now()->addYear()->format('c'),
        ]);

        $service = app(LicenseService::class);
        $result = $service->checkLicense();

        expect($result->status)->toBe('expired');
        expect($result->isActive())->toBeFalse();
        expect($result->features)->toBeNull();
    });

    it('returns invalid when API fails and no last_checked_at exists', function () {
        Http::fake([
            'https://api.opnform.com/licenses/validate' => Http::response([], 500),
        ]);

        $this->storeSelfHostedLicense([
            'license_key' => 'lic_nevervalidated12345',
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
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_features12345',
        ]);

        Cache::put('self_hosted_license_check', new LicenseCheckResult(
            status: 'active',
            features: ['sso' => true, 'multiOrg' => true, 'whitelabel' => true],
            lastChecked: now(),
        ), 86400);

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
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_appfeat12345',
        ]);

        Cache::put('self_hosted_license_check', new LicenseCheckResult(
            status: 'active',
            features: ['sso' => true, 'custom_smtp' => true],
            lastChecked: now(),
        ), 86400);

        $service = app(LicenseService::class);

        expect($service->hasAppFeature('sso.oidc'))->toBeTrue();
        expect($service->hasAppFeature('sso.saml'))->toBeTrue();
        expect($service->hasAppFeature('custom_smtp'))->toBeTrue();
        expect($service->hasAppFeature('audit_logs'))->toBeFalse();
    });
});

describe('hasPaidLicense', function () {
    it('returns false when no self-hosted license is installed', function () {
        $service = app(LicenseService::class);

        expect($service->hasPaidLicense())->toBeFalse();
    });

    it('returns true when the installed self-hosted license is active', function () {
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_paidtelemetry12345',
            'status' => 'active',
        ]);

        $service = app(LicenseService::class);

        expect($service->hasPaidLicense())->toBeTrue();
    });

    it('returns false when the installed self-hosted license is not active', function () {
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_invalidtelemetry12345',
            'status' => 'invalid',
        ]);

        $service = app(LicenseService::class);

        expect($service->hasPaidLicense())->toBeFalse();
    });

    it('uses the cached license status when available', function () {
        $this->storeSelfHostedLicense([
            'license_key' => 'lic_cachedtelemetry12345',
            'status' => 'active',
        ]);

        Cache::put('self_hosted_license_check', LicenseCheckResult::invalid(), 86400);

        $service = app(LicenseService::class);

        expect($service->hasPaidLicense())->toBeFalse();
    });
});
