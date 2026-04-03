<?php

use App\Models\License;
use App\Service\License\LicenseCheckResult;
use Illuminate\Support\Facades\Cache;

describe('feature flags include license data for self-hosted', function () {
    it('includes license status when self-hosted with active license', function () {
        config(['app.self_hosted' => true]);
        config(['cashier.key' => null]);

        License::create([
            'license_key' => 'lic_flagstest12345',
            'license_provider' => 'self_hosted_enterprise',
            'status' => 'active',
            'meta' => [],
            'features' => ['sso' => true, 'multiOrg' => true],
            'last_checked_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        Cache::put('self_hosted_license_check', new LicenseCheckResult(
            status: 'active',
            features: ['sso' => true, 'multiOrg' => true],
            lastChecked: now(),
            expiresAt: now()->addYear(),
        ), 86400);
        Cache::forget('feature_flags');

        $response = $this->getJson('/content/feature-flags');

        $response->assertStatus(200)
            ->assertJson([
                'self_hosted' => true,
            ]);

        expect($response->json('license'))->not->toBeNull();
        expect($response->json('license.status'))->toBe('active');
        expect($response->json('license.features.sso'))->toBeTrue();
    });

    it('includes license with invalid status when no license exists', function () {
        config(['app.self_hosted' => true]);
        config(['cashier.key' => null]);
        Cache::forget('feature_flags');
        Cache::forget('self_hosted_license_check');

        $response = $this->getJson('/content/feature-flags');

        $response->assertStatus(200)
            ->assertJson([
                'self_hosted' => true,
            ]);

        expect($response->json('license.status'))->toBe('invalid');
    });

    it('does not include license data for cloud instance', function () {
        config(['app.self_hosted' => false]);
        config(['cashier.key' => 'stripe_key']);
        config(['cashier.secret' => 'stripe_secret']);
        Cache::forget('feature_flags');

        $response = $this->getJson('/content/feature-flags');

        $response->assertStatus(200)
            ->assertJson([
                'self_hosted' => false,
            ]);

        expect($response->json('license'))->toBeNull();
    });
});
