<?php

namespace App\Service\License;

use App\Models\LicenseActivation;
use App\Models\LicenseCheckoutSession;
use App\Models\LicenseKey;
use App\Notifications\Subscription\LicenseKeyNotification;
use App\Service\BillingHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Stripe\BillingPortal\Session as StripeBillingPortalSession;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

class LicenseKeyService
{
    private const ACTIVATION_LIMIT = 1;

    /**
     * Create a Stripe checkout session for a self-hosted license purchase.
     */
    public function createCheckoutSession(string $billingEmail, string $plan, string $period): array
    {
        Stripe::setApiKey(config('cashier.secret'));

        $pricing = BillingHelper::getPricing($plan);
        if (!$pricing || empty($pricing[$period])) {
            throw new \InvalidArgumentException('Invalid plan or period.');
        }

        $stripeSession = StripeSession::create([
            'mode' => 'subscription',
            'customer_email' => $billingEmail,
            'line_items' => [[
                'price' => $pricing[$period],
                'quantity' => 1,
            ]],
            'success_url' => config('services.license.checkout_success_url'),
            'cancel_url' => config('services.license.checkout_cancel_url'),
            'allow_promotion_codes' => true,
            'billing_address_collection' => 'required',
            'metadata' => [
                'type' => 'self_hosted_license',
                'plan' => $plan,
                'period' => $period,
            ],
        ]);

        LicenseCheckoutSession::create([
            'stripe_session_id' => $stripeSession->id,
            'billing_email' => $billingEmail,
            'plan' => $plan,
            'period' => $period,
            'status' => LicenseCheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);

        return [
            'checkoutUrl' => $stripeSession->url,
            'sessionId' => $stripeSession->id,
        ];
    }

    /**
     * Generate a license key for a completed checkout session.
     * Idempotent for repeated webhook deliveries.
     */
    public function generateKeyForSession(string $stripeSessionId, string $stripeCustomerId, string $stripeSubscriptionId, ?\DateTimeInterface $expiresAt = null): LicenseKey
    {
        if ($stripeCustomerId === '' || $stripeSubscriptionId === '') {
            throw new \InvalidArgumentException('Checkout session is missing customer or subscription.');
        }

        return DB::transaction(function () use ($stripeSessionId, $stripeCustomerId, $stripeSubscriptionId, $expiresAt) {
            $checkoutSession = LicenseCheckoutSession::where('stripe_session_id', $stripeSessionId)
                ->lockForUpdate()
                ->first();

            if (!$checkoutSession) {
                throw new \InvalidArgumentException('Unknown license checkout session.');
            }

            if ($checkoutSession->license_key_id) {
                return $checkoutSession->licenseKey()->firstOrFail();
            }

            $licenseKey = LicenseKey::where('stripe_subscription_id', $stripeSubscriptionId)
                ->lockForUpdate()
                ->first();

            if (!$licenseKey) {
                $licenseKey = LicenseKey::create([
                    'license_key' => $this->generateKey(),
                    'stripe_customer_id' => $stripeCustomerId,
                    'stripe_subscription_id' => $stripeSubscriptionId,
                    'billing_email' => $checkoutSession->billing_email,
                    'status' => LicenseKey::STATUS_ACTIVE,
                    'plan' => $checkoutSession->plan,
                    'features' => LicenseKey::defaultEnterpriseFeatures(),
                    'expires_at' => $expiresAt,
                ]);
            }

            $checkoutSession->update([
                'license_key_id' => $licenseKey->id,
                'status' => LicenseCheckoutSession::STATUS_COMPLETED,
            ]);

            return $licenseKey;
        });
    }

    /**
     * Send the license key to the customer via email.
     */
    public function sendLicenseKeyEmail(LicenseKey $licenseKey, ?string $stripeSessionId = null): void
    {
        $checkoutSession = $stripeSessionId
            ? LicenseCheckoutSession::where('stripe_session_id', $stripeSessionId)->first()
            : LicenseCheckoutSession::where('license_key_id', $licenseKey->id)->latest('id')->first();

        if ($checkoutSession?->license_email_sent_at) {
            return;
        }

        Notification::route('mail', $licenseKey->billing_email)
            ->notify(new LicenseKeyNotification($licenseKey));

        $checkoutSession?->update([
            'license_email_sent_at' => now(),
        ]);
    }

    /**
     * Validate a license key and bind it to the calling instance.
     */
    public function validate(string $key, string $instanceId, array $usage = []): array
    {
        $licenseKey = LicenseKey::where('license_key', $key)->first();

        if (!$licenseKey) {
            return $this->invalidResponse('invalid');
        }

        $isActive = $licenseKey->isActive();

        if ($isActive && $licenseKey->stripe_subscription_id) {
            $isActive = $this->verifyStripeSubscription($licenseKey);
            $licenseKey->refresh();
        }

        if (!$isActive) {
            return [
                'valid' => false,
                'status' => 'expired',
                'features' => null,
                'expiresAt' => $licenseKey->expires_at?->toIso8601String(),
                'licenseId' => (string) $licenseKey->id,
                'activationId' => null,
            ];
        }

        $activation = $this->activateInstance($licenseKey, $instanceId, $usage);
        if (!$activation) {
            return [
                'valid' => false,
                'status' => 'activation_limit_reached',
                'features' => null,
                'expiresAt' => $licenseKey->expires_at?->toIso8601String(),
                'licenseId' => (string) $licenseKey->id,
                'activationId' => null,
            ];
        }

        return [
            'valid' => true,
            'status' => 'active',
            'features' => $licenseKey->features,
            'expiresAt' => $licenseKey->expires_at?->toIso8601String(),
            'licenseId' => (string) $licenseKey->id,
            'activationId' => (string) $activation->id,
        ];
    }

    public function createBillingPortalSession(string $key): array
    {
        $licenseKey = LicenseKey::where('license_key', $key)->first();
        if (!$licenseKey || !$licenseKey->isActive()) {
            throw new \InvalidArgumentException('License key is invalid or expired.');
        }

        if ($licenseKey->stripe_subscription_id && !$this->verifyStripeSubscription($licenseKey)) {
            throw new \InvalidArgumentException('License subscription is not active.');
        }

        if (!$licenseKey->stripe_customer_id) {
            throw new \InvalidArgumentException('License is missing a Stripe customer.');
        }

        Stripe::setApiKey(config('cashier.secret'));

        $session = StripeBillingPortalSession::create([
            'customer' => $licenseKey->stripe_customer_id,
            'return_url' => config('services.license.portal_return_url'),
        ]);

        return [
            'portalUrl' => $session->url,
        ];
    }

    /**
     * Update a license key's status when the Stripe subscription changes.
     */
    public function handleSubscriptionUpdated(string $stripeSubscriptionId, string $status, ?\DateTimeInterface $expiresAt = null): void
    {
        $licenseKey = LicenseKey::where('stripe_subscription_id', $stripeSubscriptionId)->first();
        if (!$licenseKey) {
            return;
        }

        $licenseKey->update([
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Mark a license as cancelled when subscription is deleted/cancelled.
     */
    public function handleSubscriptionDeleted(string $stripeSubscriptionId): void
    {
        $licenseKey = LicenseKey::where('stripe_subscription_id', $stripeSubscriptionId)->first();
        if (!$licenseKey) {
            return;
        }

        $licenseKey->update([
            'status' => LicenseKey::STATUS_CANCELLED,
        ]);
    }

    private function activateInstance(LicenseKey $licenseKey, string $instanceId, array $usage): ?LicenseActivation
    {
        return DB::transaction(function () use ($licenseKey, $instanceId, $usage) {
            LicenseKey::whereKey($licenseKey->id)->lockForUpdate()->first();

            $activation = LicenseActivation::where('license_key_id', $licenseKey->id)
                ->where('instance_id', $instanceId)
                ->first();

            if ($activation) {
                if ($activation->status !== LicenseActivation::STATUS_ACTIVE) {
                    return null;
                }

                $activation->update([
                    'usage' => $usage,
                    'last_seen_at' => now(),
                ]);

                return $activation;
            }

            $activeActivations = LicenseActivation::where('license_key_id', $licenseKey->id)
                ->where('status', LicenseActivation::STATUS_ACTIVE)
                ->count();

            if ($activeActivations >= self::ACTIVATION_LIMIT) {
                return null;
            }

            return LicenseActivation::create([
                'license_key_id' => $licenseKey->id,
                'instance_id' => $instanceId,
                'status' => LicenseActivation::STATUS_ACTIVE,
                'usage' => $usage,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        });
    }

    private function invalidResponse(string $status): array
    {
        return [
            'valid' => false,
            'status' => $status,
            'features' => null,
            'expiresAt' => null,
            'licenseId' => null,
            'activationId' => null,
        ];
    }

    private function generateKey(): string
    {
        return 'lic_' . bin2hex(random_bytes(20));
    }

    private function verifyStripeSubscription(LicenseKey $licenseKey): bool
    {
        try {
            Stripe::setApiKey(config('cashier.secret'));
            $subscription = \Stripe\Subscription::retrieve($licenseKey->stripe_subscription_id);

            if (in_array($subscription->status, ['active', 'trialing'], true)) {
                $periodEnd = $subscription->current_period_end ?? null;
                if ($periodEnd !== null) {
                    $licenseKey->update(['expires_at' => \Carbon\Carbon::createFromTimestamp($periodEnd)]);
                }

                return true;
            }

            $licenseKey->update(['status' => LicenseKey::STATUS_EXPIRED]);

            return false;
        } catch (\Exception $e) {
            Log::warning('Failed to verify Stripe subscription for license', [
                'license_key_id' => $licenseKey->id,
                'error' => $e->getMessage(),
            ]);

            return $licenseKey->isActive();
        }
    }
}
