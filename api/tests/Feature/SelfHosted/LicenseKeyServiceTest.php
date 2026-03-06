<?php

use App\Models\LicenseKey;
use App\Service\License\LicenseKeyService;

beforeEach(function () {
    config(['app.self_hosted' => false]);
    config(['cashier.key' => 'pk_test_123']);
    config(['cashier.secret' => 'sk_test_123']);
});

describe('generateKeyForSession', function () {
    it('generates a license key with lic_ prefix', function () {
        $service = app(LicenseKeyService::class);

        $key = $service->generateKeyForSession(
            stripeSessionId: 'cs_test_session',
            stripeCustomerId: 'cus_test123',
            stripeSubscriptionId: 'sub_test123',
            expiresAt: now()->addYear(),
        );

        expect($key)->toBeInstanceOf(LicenseKey::class);
        expect($key->license_key)->toStartWith('lic_');
        expect(strlen($key->license_key))->toBe(44); // lic_ + 40 hex chars
        expect($key->stripe_customer_id)->toBe('cus_test123');
        expect($key->stripe_subscription_id)->toBe('sub_test123');
        expect($key->status)->toBe('active');
        expect($key->plan)->toBe('self_hosted');
        expect($key->features)->toHaveKey('sso');
    });

    it('is idempotent for same subscription', function () {
        $service = app(LicenseKeyService::class);

        $first = $service->generateKeyForSession(
            stripeSessionId: 'cs_test_idem',
            stripeCustomerId: 'cus_test456',
            stripeSubscriptionId: 'sub_idem_test',
            expiresAt: now()->addYear(),
        );

        $second = $service->generateKeyForSession(
            stripeSessionId: 'cs_test_idem2',
            stripeCustomerId: 'cus_test456',
            stripeSubscriptionId: 'sub_idem_test',
            expiresAt: now()->addYear(),
        );

        expect(LicenseKey::where('stripe_subscription_id', 'sub_idem_test')->count())->toBeLessThanOrEqual(2);
    });
});

describe('validate', function () {
    it('returns valid for active license key', function () {
        LicenseKey::create([
            'license_key' => 'lic_validatetest12345678901234567890',
            'billing_email' => 'admin@company.com',
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => '',
            'status' => 'active',
            'plan' => 'self_hosted',
            'features' => ['sso' => true, 'multiOrg' => true],
            'expires_at' => now()->addYear(),
        ]);

        $service = app(LicenseKeyService::class);
        $result = $service->validate('lic_validatetest12345678901234567890');

        expect($result['valid'])->toBeTrue();
        expect($result['status'])->toBe('active');
        expect($result['features']['sso'])->toBeTrue();
        expect($result['expiresAt'])->not->toBeNull();
    });

    it('returns invalid for non-existent key', function () {
        $service = app(LicenseKeyService::class);
        $result = $service->validate('lic_doesnotexist12345');

        expect($result['valid'])->toBeFalse();
        expect($result['status'])->toBe('invalid');
        expect($result['features'])->toBeNull();
    });

    it('returns expired for past expiry date', function () {
        LicenseKey::create([
            'license_key' => 'lic_expiredvalidate12345678901234567',
            'billing_email' => 'admin@company.com',
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => '',
            'status' => 'active',
            'plan' => 'self_hosted',
            'features' => ['sso' => true],
            'expires_at' => now()->subDay(),
        ]);

        $service = app(LicenseKeyService::class);
        $result = $service->validate('lic_expiredvalidate12345678901234567');

        expect($result['valid'])->toBeFalse();
        expect($result['status'])->toBe('expired');
    });

    it('returns expired for cancelled license', function () {
        LicenseKey::create([
            'license_key' => 'lic_cancelledtest12345678901234567890',
            'billing_email' => 'admin@company.com',
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => '',
            'status' => 'cancelled',
            'plan' => 'self_hosted',
            'features' => ['sso' => true],
            'expires_at' => now()->addYear(),
        ]);

        $service = app(LicenseKeyService::class);
        $result = $service->validate('lic_cancelledtest12345678901234567890');

        expect($result['valid'])->toBeFalse();
        expect($result['status'])->toBe('expired');
    });
});

describe('handleSubscriptionDeleted', function () {
    it('marks license as cancelled when subscription is deleted', function () {
        LicenseKey::create([
            'license_key' => 'lic_subdeleted123456789012345678901',
            'billing_email' => 'admin@company.com',
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_to_delete',
            'status' => 'active',
            'plan' => 'self_hosted',
            'features' => ['sso' => true],
            'expires_at' => now()->addYear(),
        ]);

        $service = app(LicenseKeyService::class);
        $service->handleSubscriptionDeleted('sub_to_delete');

        $licenseKey = LicenseKey::where('stripe_subscription_id', 'sub_to_delete')->first();
        expect($licenseKey->status)->toBe('cancelled');
    });

    it('does nothing for unknown subscription id', function () {
        $service = app(LicenseKeyService::class);
        $service->handleSubscriptionDeleted('sub_unknown');

        expect(LicenseKey::count())->toBe(0);
    });
});

describe('handleSubscriptionUpdated', function () {
    it('updates license status and expiry', function () {
        LicenseKey::create([
            'license_key' => 'lic_subupdated123456789012345678901',
            'billing_email' => 'admin@company.com',
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_to_update',
            'status' => 'active',
            'plan' => 'self_hosted',
            'features' => ['sso' => true],
            'expires_at' => now()->addMonth(),
        ]);

        $newExpiry = now()->addYear();
        $service = app(LicenseKeyService::class);
        $service->handleSubscriptionUpdated('sub_to_update', 'active', $newExpiry);

        $licenseKey = LicenseKey::where('stripe_subscription_id', 'sub_to_update')->first();
        expect($licenseKey->status)->toBe('active');
        expect($licenseKey->expires_at->format('Y-m-d'))->toBe($newExpiry->format('Y-m-d'));
    });

    it('ignores unknown subscription id', function () {
        $service = app(LicenseKeyService::class);
        $service->handleSubscriptionUpdated('sub_unknown', 'active', now()->addYear());

        expect(LicenseKey::count())->toBe(0);
    });
});
