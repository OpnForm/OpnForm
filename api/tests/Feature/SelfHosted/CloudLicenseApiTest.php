<?php

use App\Models\LicenseActivation;
use App\Models\LicenseKey;

beforeEach(function () {
    config(['app.self_hosted' => false]);
    config(['cashier.key' => 'pk_test_123']);
    config(['cashier.secret' => 'sk_test_123']);
});

describe('POST /licenses/validate', function () {
    it('returns invalid for non-existent key', function () {
        $response = $this->postJson('/licenses/validate', [
            'licenseKey' => 'lic_nonexistent12345678901234567890',
            'instanceId' => 'instance-1',
        ]);

        $response->assertSuccessful()
            ->assertJson([
                'valid' => false,
                'status' => 'invalid',
            ]);
    });

    it('returns valid for active license key', function () {
        LicenseKey::create([
            'license_key' => 'lic_cloudvalidkey1234567890123456789',
            'billing_email' => 'admin@company.com',
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => '',
            'status' => 'active',
            'plan' => 'self_hosted',
            'features' => ['sso' => true, 'multiOrg' => true],
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->postJson('/licenses/validate', [
            'licenseKey' => 'lic_cloudvalidkey1234567890123456789',
            'instanceId' => 'instance-1',
            'usage' => ['userCount' => 1],
        ]);

        $response->assertSuccessful()
            ->assertJson([
                'valid' => true,
                'status' => 'active',
            ]);

        expect($response->json('features.sso'))->toBeTrue();
        expect($response->json('activationId'))->not->toBeNull();
        expect(LicenseActivation::where('instance_id', 'instance-1')->exists())->toBeTrue();
    });

    it('allows the same instance to revalidate the same license key', function () {
        LicenseKey::create([
            'license_key' => 'lic_revalidkey12345678901234567890',
            'billing_email' => 'admin@company.com',
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => '',
            'status' => 'active',
            'plan' => 'self_hosted',
            'features' => ['sso' => true],
            'expires_at' => now()->addYear(),
        ]);

        $payload = [
            'licenseKey' => 'lic_revalidkey12345678901234567890',
            'instanceId' => 'instance-1',
            'usage' => ['userCount' => 1],
        ];

        $this->postJson('/licenses/validate', $payload)->assertSuccessful();
        $response = $this->postJson('/licenses/validate', array_merge($payload, [
            'usage' => ['userCount' => 2],
        ]));

        $response->assertSuccessful()
            ->assertJson([
                'valid' => true,
                'status' => 'active',
            ]);

        expect(LicenseActivation::count())->toBe(1);
        expect(LicenseActivation::first()->usage)->toBe(['userCount' => 2]);
    });

    it('blocks activating the same commercial key on a second instance', function () {
        LicenseKey::create([
            'license_key' => 'lic_oneinstance12345678901234567890',
            'billing_email' => 'admin@company.com',
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => '',
            'status' => 'active',
            'plan' => 'self_hosted',
            'features' => ['sso' => true],
            'expires_at' => now()->addYear(),
        ]);

        $this->postJson('/licenses/validate', [
            'licenseKey' => 'lic_oneinstance12345678901234567890',
            'instanceId' => 'instance-1',
        ])->assertSuccessful();

        $response = $this->postJson('/licenses/validate', [
            'licenseKey' => 'lic_oneinstance12345678901234567890',
            'instanceId' => 'instance-2',
        ]);

        $response->assertSuccessful()
            ->assertJson([
                'valid' => false,
                'status' => 'activation_limit_reached',
            ]);

        expect(LicenseActivation::count())->toBe(1);
    });

    it('returns invalid for expired license key', function () {
        LicenseKey::create([
            'license_key' => 'lic_expiredcloudkey123456789012345678',
            'billing_email' => 'admin@company.com',
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => '',
            'status' => 'active',
            'plan' => 'self_hosted',
            'features' => ['sso' => true],
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/licenses/validate', [
            'licenseKey' => 'lic_expiredcloudkey123456789012345678',
            'instanceId' => 'instance-1',
        ]);

        $response->assertSuccessful()
            ->assertJson([
                'valid' => false,
                'status' => 'expired',
            ]);
    });

    it('validates licenseKey is required', function () {
        $response = $this->postJson('/licenses/validate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['licenseKey', 'instanceId']);
    });
});

describe('GET /licenses/{licenseKey}', function () {
    it('does not expose license details through a URL containing the key', function () {
        LicenseKey::create([
            'license_key' => 'lic_showkey12345678901234567890123456',
            'billing_email' => 'admin@company.com',
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => '',
            'status' => 'active',
            'plan' => 'self_hosted',
            'features' => ['sso' => true, 'multiOrg' => true],
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->getJson('/licenses/lic_showkey12345678901234567890123456');

        $response->assertStatus(404);
    });
});

describe('POST /licenses/create', function () {
    it('validates required fields', function () {
        $response = $this->postJson('/licenses/create', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['billingEmail', 'plan', 'period']);
    });

    it('validates plan must be self_hosted', function () {
        $response = $this->postJson('/licenses/create', [
            'billingEmail' => 'test@example.com',
            'plan' => 'pro',
            'period' => 'yearly',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    });

    it('validates period must be monthly or yearly', function () {
        $response = $this->postJson('/licenses/create', [
            'billingEmail' => 'test@example.com',
            'plan' => 'self_hosted',
            'period' => 'weekly',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['period']);
    });
});
