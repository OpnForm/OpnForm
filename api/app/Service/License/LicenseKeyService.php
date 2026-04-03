<?php

namespace App\Service\License;

use App\Models\LicenseCheckoutSession;
use App\Models\LicenseKey;
use App\Notifications\Subscription\LicenseKeyNotification;
use App\Service\BillingHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

class LicenseKeyService
{
    /**
     * Create a Stripe checkout session for a self-hosted license purchase.
     */
    public function createCheckoutSession(string $billingEmail, string $plan, string $period, string $successUrl, string $cancelUrl): array
    {
        Stripe::setApiKey(config('cashier.secret'));

        $pricing = BillingHelper::getPricing($plan);
        if (!$pricing || !isset($pricing[$period])) {
            throw new \InvalidArgumentException('Invalid plan or period.');
        }

        $stripeSession = StripeSession::create([
            'mode' => 'subscription',
            'customer_email' => $billingEmail,
            'line_items' => [[
                'price' => $pricing[$period],
                'quantity' => 1,
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
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
     * Idempotent — returns existing key if already generated for this session.
     */
    public function generateKeyForSession(string $stripeSessionId, string $stripeCustomerId, string $stripeSubscriptionId, ?\DateTimeInterface $expiresAt = null): LicenseKey
    {
        $checkoutSession = LicenseCheckoutSession::where('stripe_session_id', $stripeSessionId)->first();

        if ($checkoutSession && $checkoutSession->license_key_id) {
            return $checkoutSession->licenseKey;
        }

        $licenseKey = LicenseKey::create([
            'license_key' => $this->generateKey(),
            'stripe_customer_id' => $stripeCustomerId,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'billing_email' => $checkoutSession?->billing_email ?? '',
            'status' => LicenseKey::STATUS_ACTIVE,
            'plan' => $checkoutSession?->plan ?? 'self_hosted',
            'features' => LicenseKey::defaultEnterpriseFeatures(),
            'expires_at' => $expiresAt,
        ]);

        if ($checkoutSession) {
            $checkoutSession->update([
                'license_key_id' => $licenseKey->id,
                'status' => LicenseCheckoutSession::STATUS_COMPLETED,
            ]);
        }

        return $licenseKey;
    }

    /**
     * Send the license key to the customer via email.
     */
    public function sendLicenseKeyEmail(LicenseKey $licenseKey): void
    {
        Notification::route('mail', $licenseKey->billing_email)
            ->notify(new LicenseKeyNotification($licenseKey));
    }

    /**
     * Validate a license key and return its status.
     */
    public function validate(string $key): array
    {
        $licenseKey = LicenseKey::where('license_key', $key)->first();

        if (!$licenseKey) {
            return [
                'valid' => false,
                'status' => 'invalid',
                'features' => null,
                'expiresAt' => null,
            ];
        }

        $isActive = $licenseKey->isActive();

        if ($isActive && $licenseKey->stripe_subscription_id) {
            $isActive = $this->verifyStripeSubscription($licenseKey);
        }

        return [
            'valid' => $isActive,
            'status' => $isActive ? 'active' : 'expired',
            'features' => $isActive ? $licenseKey->features : null,
            'expiresAt' => $licenseKey->expires_at?->toIso8601String(),
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
     * Mark a license as expired when subscription is deleted/cancelled.
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

    private function generateKey(): string
    {
        return 'lic_' . bin2hex(random_bytes(20));
    }

    private function verifyStripeSubscription(LicenseKey $licenseKey): bool
    {
        try {
            Stripe::setApiKey(config('cashier.secret'));
            $subscription = \Stripe\Subscription::retrieve($licenseKey->stripe_subscription_id);

            if (in_array($subscription->status, ['active', 'trialing'])) {
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
