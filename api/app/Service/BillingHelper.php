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
            $productId = self::getProductId('default');

            if (!$productId) {
                return null;
            }

            // Find the main subscription line item for the default product
            $mainItem = $lineItems->first(function ($lineItem) use ($productId) {
                return $lineItem->price->product === $productId;
            });

            if (!$mainItem) {
                return null;
            }

            // Check the actual billing interval from Stripe
            return self::getLineItemInterval($mainItem);
        } catch (\Exception $e) {
            // If we can't fetch from Stripe, fall back to false
            return null;
        }
    }
}
