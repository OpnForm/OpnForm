<?php

use App\Models\LicenseActivation;
use App\Models\LicenseCheckoutSession;
use App\Models\LicenseKey;
use App\Notifications\Subscription\LicenseKeyNotification;
use App\Service\License\LicenseKeyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['app.self_hosted' => false]);
    config(['cashier.key' => 'pk_test_123']);
    config(['cashier.secret' => 'sk_test_123']);
});

function createLicenseCheckoutSession(string $stripeSessionId = 'cs_test_session'): LicenseCheckoutSession
{
    return LicenseCheckoutSession::create([
        'stripe_session_id' => $stripeSessionId,
        'billing_email' => 'admin@company.com',
        'plan' => 'self_hosted',
        'period' => 'yearly',
        'status' => LicenseCheckoutSession::STATUS_PENDING,
        'expires_at' => now()->addMinutes(30),
    ]);
}

describe('generateKeyForSession', function () {
    it('generates a license key with lic_ prefix for a known checkout session', function () {
        createLicenseCheckoutSession('cs_test_session');

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
        expect(LicenseCheckoutSession::where('stripe_session_id', 'cs_test_session')->value('license_key_id'))->toBe($key->id);
    });

    it('is idempotent for the same checkout session', function () {
        createLicenseCheckoutSession('cs_test_idem');

        $service = app(LicenseKeyService::class);

        $first = $service->generateKeyForSession(
            stripeSessionId: 'cs_test_idem',
            stripeCustomerId: 'cus_test456',
            stripeSubscriptionId: 'sub_idem_test',
            expiresAt: now()->addYear(),
        );

        $second = $service->generateKeyForSession(
            stripeSessionId: 'cs_test_idem',
            stripeCustomerId: 'cus_test456',
            stripeSubscriptionId: 'sub_idem_test',
            expiresAt: now()->addYear(),
        );

        expect($second->id)->toBe($first->id);
        expect(LicenseKey::where('stripe_subscription_id', 'sub_idem_test')->count())->toBe(1);
    });

    it('rejects unknown checkout sessions so Stripe can retry', function () {
        $service = app(LicenseKeyService::class);

        $service->generateKeyForSession(
            stripeSessionId: 'cs_unknown',
            stripeCustomerId: 'cus_test456',
            stripeSubscriptionId: 'sub_unknown',
            expiresAt: now()->addYear(),
        );
    })->throws(\InvalidArgumentException::class);
});

describe('sendLicenseKeyEmail', function () {
    it('sends the license email synchronously before marking it sent', function () {
        $checkoutSession = createLicenseCheckoutSession('cs_email_test');
        $licenseKey = app(LicenseKeyService::class)->generateKeyForSession(
            stripeSessionId: 'cs_email_test',
            stripeCustomerId: 'cus_email_test',
            stripeSubscriptionId: 'sub_email_test',
            expiresAt: now()->addYear(),
        );

        Notification::fake();

        app(LicenseKeyService::class)->sendLicenseKeyEmail($licenseKey, 'cs_email_test');

        Notification::assertSentOnDemand(LicenseKeyNotification::class);
        expect(class_implements(LicenseKeyNotification::class))->not->toContain(ShouldQueue::class);
        expect($checkoutSession->refresh()->license_email_sent_at)->not->toBeNull();
    });
});

describe('validate', function () {
    it('returns valid for active license key and creates an activation', function () {
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
        $result = $service->validate('lic_validatetest12345678901234567890', 'instance-1', ['userCount' => 1]);

        expect($result['valid'])->toBeTrue();
        expect($result['status'])->toBe('active');
        expect($result['features']['sso'])->toBeTrue();
        expect($result['expiresAt'])->not->toBeNull();
        expect($result['activationId'])->not->toBeNull();
        expect(LicenseActivation::where('instance_id', 'instance-1')->exists())->toBeTrue();
    });

    it('returns invalid for non-existent key', function () {
        $service = app(LicenseKeyService::class);
        $result = $service->validate('lic_doesnotexist12345', 'instance-1');

        expect($result['valid'])->toBeFalse();
        expect($result['status'])->toBe('invalid');
        expect($result['features'])->toBeNull();
    });

    it('returns activation_limit_reached for a second instance', function () {
        LicenseKey::create([
            'license_key' => 'lic_limitvalidate12345678901234567',
            'billing_email' => 'admin@company.com',
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => '',
            'status' => 'active',
            'plan' => 'self_hosted',
            'features' => ['sso' => true],
            'expires_at' => now()->addYear(),
        ]);

        $service = app(LicenseKeyService::class);
        $service->validate('lic_limitvalidate12345678901234567', 'instance-1');
        $result = $service->validate('lic_limitvalidate12345678901234567', 'instance-2');

        expect($result['valid'])->toBeFalse();
        expect($result['status'])->toBe('activation_limit_reached');
        expect(LicenseActivation::count())->toBe(1);
    });

    it('does not reactivate a revoked activation record', function () {
        LicenseKey::create([
            'license_key' => 'lic_revokedvalidate123456789012345',
            'billing_email' => 'admin@company.com',
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => '',
            'status' => 'active',
            'plan' => 'self_hosted',
            'features' => ['sso' => true],
            'expires_at' => now()->addYear(),
        ]);

        $service = app(LicenseKeyService::class);
        $service->validate('lic_revokedvalidate123456789012345', 'instance-1');

        LicenseActivation::where('instance_id', 'instance-1')->update([
            'status' => LicenseActivation::STATUS_REVOKED,
        ]);

        $secondInstance = $service->validate('lic_revokedvalidate123456789012345', 'instance-2');
        $revokedInstance = $service->validate('lic_revokedvalidate123456789012345', 'instance-1');

        expect($secondInstance['valid'])->toBeTrue();
        expect($revokedInstance['valid'])->toBeFalse();
        expect($revokedInstance['status'])->toBe('activation_limit_reached');
        expect(LicenseActivation::where('status', LicenseActivation::STATUS_ACTIVE)->count())->toBe(1);
        expect(LicenseActivation::where('instance_id', 'instance-1')->value('status'))->toBe(LicenseActivation::STATUS_REVOKED);
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
        $result = $service->validate('lic_expiredvalidate12345678901234567', 'instance-1');

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
        $result = $service->validate('lic_cancelledtest12345678901234567890', 'instance-1');

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
