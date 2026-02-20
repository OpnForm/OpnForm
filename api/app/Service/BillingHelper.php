<?php

namespace App\Service;

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

    public static function getLineItemInterval(SubscriptionItem $item)
    {
        return $item->price->recurring->interval === 'year' ? 'yearly' : 'monthly';
    }

    public static function getSubscriptionInterval(Subscription $subscription)
    {
        try {
            $stripeSub = $subscription->asStripeSubscription();
            $lineItems = collect($stripeSub->items);

            // Check all possible product IDs (pro, business, enterprise, and legacy default)
            $productNames = ['pro', 'business', 'enterprise', 'default'];
            $productIds = array_filter(array_map(fn ($name) => self::getProductId($name), $productNames));

            if (empty($productIds)) {
                return null;
            }

            // Find the main subscription line item for any known product
            $mainItem = $lineItems->first(function ($lineItem) use ($productIds) {
                return in_array($lineItem->price->product, $productIds);
            });

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
