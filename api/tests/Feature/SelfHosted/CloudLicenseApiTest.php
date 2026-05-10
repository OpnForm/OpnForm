<?php

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
        ]);

        $response->assertSuccessful()
            ->assertJson([
                'valid' => true,
                'status' => 'active',
            ]);

        expect($response->json('features.sso'))->toBeTrue();
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
            ->assertJsonValidationErrors(['licenseKey']);
    });
});

describe('GET /licenses/{licenseKey}', function () {
    it('returns license details for valid key', function () {
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

        $response->assertSuccessful()
            ->assertJson([
                'licenseKey' => 'lic_showkey12345678901234567890123456',
                'status' => 'active',
                'plan' => 'self_hosted',
            ]);

        expect($response->json('features.sso'))->toBeTrue();
        expect($response->json('expiresAt'))->not->toBeNull();
    });

    it('returns 404 for non-existent key', function () {
        $response = $this->getJson('/licenses/lic_doesnotexist12345678901234567');

        $response->assertStatus(404)
            ->assertJson(['error' => 'License not found.']);
    });
});

describe('POST /licenses/create', function () {
    it('validates required fields', function () {
        $response = $this->postJson('/licenses/create', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['billingEmail', 'plan', 'period', 'successUrl', 'cancelUrl']);
    });

    it('validates plan must be self_hosted', function () {
        $response = $this->postJson('/licenses/create', [
            'billingEmail' => 'test@example.com',
            'plan' => 'pro',
            'period' => 'yearly',
            'successUrl' => 'https://opnform.com/self-hosted/checkout/success',
            'cancelUrl' => 'https://opnform.com/self-hosted/checkout/canceled',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    });

    it('validates period must be monthly or yearly', function () {
        $response = $this->postJson('/licenses/create', [
            'billingEmail' => 'test@example.com',
            'plan' => 'self_hosted',
            'period' => 'weekly',
            'successUrl' => 'https://opnform.com/self-hosted/checkout/success',
            'cancelUrl' => 'https://opnform.com/self-hosted/checkout/canceled',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['period']);
    });
});
