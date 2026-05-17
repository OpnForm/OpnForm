<?php

namespace App\Http\Controllers\Webhook;

use App\Notifications\Subscription\FailedPaymentNotification;
use App\Service\BillingHelper;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Stripe\Subscription as StripeSubscription;

class StripeController extends WebhookController
{
    public function handleCustomerSubscriptionCreated(array $payload)
    {
        return parent::handleCustomerSubscriptionCreated($payload);
    }

    /**
     * Override to add a sleep, and to detect plan upgrades
     *
     * @return \Symfony\Component\HttpFoundation\Response|void
     */
    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        sleep(1);

        if ($user = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            $data = $payload['data']['object'];

            // We keep one local row per Stripe subscription id. If duplicates exist for the same
            // logical plan, application checks always resolve entitlement from the newest active/trialing row.
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
            $subscription->stripe_price = $mainItem['price']['id'] ?? ($isSinglePrice ? $data['items']['data'][0]['price']['id'] ?? null : null);

            // Type - previously (Name)
            $subscription->type = $this->getSubscriptionName($mainItem['price']['product'] ?? null) ?? $subscription->type ?? $this->newSubscriptionName($payload);

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
            return $this->getSubscriptionName($item['price']['product'] ?? null) !== null;
        }) ?? ($items[0] ?? []);
    }

    private function getSubscriptionName(?string $stripeProductId): ?string
    {
        return BillingHelper::getSubscriptionNameByProductId($stripeProductId);
    }
}
