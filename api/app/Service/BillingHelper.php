<?php

namespace App\Service;

use App\Models\Billing\Subscription as BillingSubscription;
use App\Service\Billing\PlanTier;
use Illuminate\Support\Facades\App;
use Laravel\Cashier\Subscription;
use Stripe\SubscriptionItem;

class BillingHelper
{
    public static function getPricing($productName = 'default')
    {
        return App::environment() == 'production' ?
            config('pricing.production.' . $productName . '.pricing') :
            config('pricing.test.' . $productName . '.pricing');
    }

    public static function getProductId($productName = 'default')
    {
        return App::environment() == 'production' ?
            config('pricing.production.' . $productName . '.product_id') :
            config('pricing.test.' . $productName . '.product_id');
    }

    public static function getKnownPlanProductIds(): array
    {
        $mapping = config('billing_state.product_tier_mapping', []);

        return array_values(array_filter(array_map(
            fn (string $productName) => self::getProductId($productName),
            array_keys($mapping)
        )));
    }

    public static function getKnownPlanPriceIds(): array
    {
        $productNames = array_keys(config('billing_state.product_tier_mapping', []));
        $priceIds = [];

        foreach ($productNames as $productName) {
            $pricing = self::getPricing($productName);
            if (!is_array($pricing)) {
                continue;
            }
            foreach ($pricing as $priceId) {
                if ($priceId) {
                    $priceIds[] = $priceId;
                }
            }
        }

        return array_values(array_unique(array_filter($priceIds)));
    }

    public static function getTierForSubscription(BillingSubscription $subscription): ?string
    {
        $mapping = config('billing_state.product_tier_mapping', []);

        return $mapping[$subscription->type] ?? config('plans.subscription_tier_mapping.' . $subscription->type, PlanTier::PRO);
    }

    public static function isGrandfatheredPriceId(?string $priceId): bool
    {
        if (!$priceId) {
            return false;
        }

        return in_array($priceId, config('billing_state.grandfathered_prices', []), true);
    }

    public static function getSubscriptionNameByProductId(?string $productId): ?string
    {
        if (!$productId) {
            return null;
        }

        $productNames = array_keys(config('billing_state.product_tier_mapping', []));
        foreach ($productNames as $productName) {
            if (self::getProductId($productName) === $productId) {
                return $productName;
            }
        }

        return null;
    }

    public static function getMainPlanLineItem(iterable $lineItems)
    {
        $productIds = self::getKnownPlanProductIds();

        foreach ($lineItems as $lineItem) {
            if (in_array($lineItem->price->product, $productIds, true)) {
                return $lineItem;
            }
        }

        return null;
    }

    public static function getLineItemInterval(SubscriptionItem $item)
    {
        return $item->price->recurring->interval === 'year' ? 'yearly' : 'monthly';
    }

    public static function getSubscriptionInterval(Subscription $subscription)
    {
        try {
            $stripeSub = $subscription->asStripeSubscription();
            $mainItem = self::getMainPlanLineItem($stripeSub->items);

            if (!$mainItem) {
                return null;
            }

            // Check the actual billing interval from Stripe
            return self::getLineItemInterval($mainItem);
        } catch (\Exception $e) {
            // If we can't fetch from Stripe, fall back to null
            return null;
        }
    }
}
