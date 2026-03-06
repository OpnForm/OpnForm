<?php

namespace App\Http\Controllers\Webhook;

use App\Notifications\Subscription\FailedPaymentNotification;
use App\Service\License\LicenseKeyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Stripe\Subscription as StripeSubscription;

class StripeController extends WebhookController
{
    public function handleCustomerSubscriptionCreated(array $payload)
    {
        return parent::handleCustomerSubscriptionCreated($payload);
    }

    /**
     * Handle checkout.session.completed for self-hosted license purchases.
     */
    protected function handleCheckoutSessionCompleted(array $payload)
    {
        $session = $payload['data']['object'];
        $metadata = $session['metadata'] ?? [];

        if (($metadata['type'] ?? '') !== 'self_hosted_license') {
            return $this->successMethod();
        }

        try {
            $service = app(LicenseKeyService::class);

            $expiresAt = null;
            if (!empty($session['subscription'])) {
                \Stripe\Stripe::setApiKey(config('cashier.secret'));
                $subscription = \Stripe\Subscription::retrieve($session['subscription']);
                $periodEnd = $subscription->current_period_end ?? null;
                if ($periodEnd !== null) {
                    $expiresAt = Carbon::createFromTimestamp($periodEnd);
                }
            }

            $licenseKey = $service->generateKeyForSession(
                stripeSessionId: $session['id'],
                stripeCustomerId: $session['customer'] ?? '',
                stripeSubscriptionId: $session['subscription'] ?? '',
                expiresAt: $expiresAt,
            );

            $service->sendLicenseKeyEmail($licenseKey);

            Log::info('Self-hosted license key generated and emailed', [
                'session_id' => $session['id'],
                'license_key_id' => $licenseKey->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process self-hosted license checkout', [
                'session_id' => $session['id'],
                'error' => $e->getMessage(),
            ]);
        }

        return $this->successMethod();
    }

    /**
     * Override to add a sleep, and to detect plan upgrades.
     * Also handles self-hosted license subscription updates.
     *
     * @return \Symfony\Component\HttpFoundation\Response|void
     */
    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        sleep(1);

        $this->updateSelfHostedLicenseFromSubscription($payload);

        if ($user = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            $data = $payload['data']['object'];

            $subscription = $user->subscriptions()->firstOrNew(['stripe_id' => $data['id']]);

            if (
                isset($data['status']) &&
                $data['status'] === StripeSubscription::STATUS_INCOMPLETE_EXPIRED
            ) {
                $subscription->items()->delete();
                $subscription->delete();

                return;
            }

            $subscription->type = $subscription->type ?? $data['metadata']['name'] ?? $this->newSubscriptionName($payload);

            $mainItem = $this->getMainSubscriptionLineItem($data['items']['data']);
            $isSinglePrice = count($data['items']['data']) === 1;

            // Price...
            $subscription->stripe_price = $isSinglePrice ? $mainItem['price']['id'] : null;

            // Type - previously (Name)
            $subscription->type = $this->getSubscriptionName($mainItem['price']['product']);

            // Quantity...
            $subscription->quantity = $isSinglePrice && isset($mainItem['quantity']) ? $mainItem['quantity'] : null;

            // Trial ending date...
            if (isset($data['trial_end'])) {
                $trialEnd = Carbon::createFromTimestamp($data['trial_end']);

                if (! $subscription->trial_ends_at || $subscription->trial_ends_at->ne($trialEnd)) {
                    $subscription->trial_ends_at = $trialEnd;
                }
            }

            // Cancellation date...
            if (isset($data['cancel_at_period_end'])) {
                if ($data['cancel_at_period_end']) {
                    $subscription->ends_at = $subscription->onTrial()
                        ? $subscription->trial_ends_at
                        : Carbon::createFromTimestamp($data['current_period_end']);
                } elseif (isset($data['cancel_at'])) {
                    $subscription->ends_at = Carbon::createFromTimestamp($data['cancel_at']);
                } else {
                    $subscription->ends_at = null;
                }
            }

            // Status...
            if (isset($data['status'])) {
                $subscription->stripe_status = $data['status'];
            }

            $subscription->save();

            // Update subscription items...
            if (isset($data['items'])) {
                $prices = [];

                foreach ($data['items']['data'] as $item) {
                    $prices[] = $item['price']['id'];

                    $subscription->items()->updateOrCreate([
                        'stripe_id' => $item['id'],
                    ], [
                        'stripe_product' => $item['price']['product'],
                        'stripe_price' => $item['price']['id'],
                        'quantity' => $item['quantity'] ?? null,
                    ]);
                }

                // Delete items that aren't attached to the subscription anymore...
                $subscription->items()->whereNotIn('stripe_price', $prices)->delete();
            }
        }

        return $this->successMethod();
    }

    protected function handleChargeFailed(array $payload)
    {
        if ($user = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            $user->notify(new FailedPaymentNotification());
        }

        return $this->successMethod();
    }

    private function getMainSubscriptionLineItem(array $items)
    {
        return collect($items)->first(function ($item) {
            return in_array($this->getSubscriptionName($item['price']['product']), ['default']);
        });
    }

    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        $data = $payload['data']['object'];

        if (!empty($data['id'])) {
            app(LicenseKeyService::class)->handleSubscriptionDeleted($data['id']);
        }

        return parent::handleCustomerSubscriptionDeleted($payload);
    }

    private function updateSelfHostedLicenseFromSubscription(array $payload): void
    {
        $data = $payload['data']['object'];
        $subscriptionId = $data['id'] ?? null;
        if (!$subscriptionId) {
            return;
        }

        $status = match ($data['status'] ?? '') {
            'active', 'trialing' => \App\Models\LicenseKey::STATUS_ACTIVE,
            'canceled', 'unpaid', 'incomplete_expired' => \App\Models\LicenseKey::STATUS_EXPIRED,
            default => null,
        };

        if (!$status) {
            return;
        }

        $expiresAt = isset($data['current_period_end'])
            ? Carbon::createFromTimestamp($data['current_period_end'])
            : null;

        app(LicenseKeyService::class)->handleSubscriptionUpdated($subscriptionId, $status, $expiresAt);
    }

    private function getSubscriptionName(string $stripeProductId)
    {
        $config = App::environment() == 'production' ? config('pricing.production') : config('pricing.test');
        foreach ($config as $plan => $data) {
            if ($stripeProductId == $config[$plan]['product_id']) {
                return $plan;
            }
        }

        return 'default';
    }
}
