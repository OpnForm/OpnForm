<?php

namespace App\Jobs\Billing;

use App\Models\Billing\Subscription;
use App\Models\Workspace;
use App\Service\BillingHelper;
use App\Service\UserHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Laravel\Cashier\Cashier;

/**
 * Update subscription with extra users when workspace users are updated.
 */
class WorkspaceUsersUpdated implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Workspace $workspace)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // If self-hosted, no need to update billing
        if (!pricing_enabled() || app()->environment('testing')) {
            return;
        }

        /*
         * @var User $billingOwner
         */
        $billingOwner = $this->workspace->billingOwners()->first();

        if (!$billingOwner || !$billingOwner->is_subscribed) {
            // If somehow billing owner is not found or not subscribed, no need to update billing
            return;
        }

        if ($billingOwner->activeLicense()) {
            // No need to update billing if user has a fixed license
            return;
        }

        // Now update the subscription accordingly
        $subscription = $billingOwner->activeDefaultSubscription();
        if (!$subscription) {
            return;
        }
        $totalUsersCount = (new UserHelper($billingOwner))->getActiveMembersCount() - 1;
        $this->updateSubscriptionWithExtraUsers($subscription, $totalUsersCount);
    }

    private function updateSubscriptionWithExtraUsers(Subscription $subscription, int $quantity): void
    {
        $stripe = Cashier::stripe();
        $extraUserPricing = BillingHelper::getPricing('extra_user');
        $stripeSub = $subscription->asStripeSubscription();
        $lineItems = collect($stripeSub->items);

        // Make sure Stripe sub has the right pro-rating settings
        $stripe->subscriptions->update($stripeSub->id, [
            'proration_behavior' => 'always_invoice',
        ]);

        // Main sub info
        $mainSubscriptionItem = $this->getLineItem($lineItems, 'default');
        $subscriptionInterval = BillingHelper::getLineItemInterval($mainSubscriptionItem);

        $extraUserLineItem = $this->getLineItem($lineItems, 'extra_user');

        if ($quantity === 0) {
            // If quantity is 0, remove the line item if it exists
            if ($extraUserLineItem) {
                $stripe->subscriptionItems->delete($extraUserLineItem->id);
            }
        } else {
            // If quantity is greater than 0
            if ($extraUserLineItem) {
                // Update existing line item
                $stripe->subscriptionItems->update(
                    $extraUserLineItem->id,
                    ['quantity' => $quantity]
                );
            } else {
                // Create new line item
                $stripeSub->items->create([
                    'price' => $extraUserPricing[$subscriptionInterval],
                    'quantity' => $quantity,
                ]);
            }
        }
    }

    private function getLineItem(Collection $lineItems, string $productName)
    {
        $productId = BillingHelper::getProductId($productName);
        if (!$productId) {
            return null;
        }
        return $lineItems->first(function ($lineItem) use ($productId) {
            return $lineItem->price->product === $productId;
        });
    }
}
