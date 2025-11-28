<?php

namespace App\Http\Controllers;

use App\Http\Requests\Subscriptions\UpdateStripeDetailsRequest;
use App\Service\BillingHelper;
use App\Service\UserHelper;
use Illuminate\Support\Facades\Auth;
use Laravel\Cashier\Subscription;

class SubscriptionController extends Controller
{
    public const SUBSCRIPTION_PLANS = ['monthly', 'yearly'];

    public const PRO_SUBSCRIPTION_NAME = 'default';

    public const SUBSCRIPTION_NAMES = [
        self::PRO_SUBSCRIPTION_NAME,
    ];

    /**
     * Returns stripe checkout URL
     *
     * $plan is constrained with regex in the api.php
     */
    public function checkout($pricing, $plan, $trial = null)
    {
        $this->middleware('not-subscribed');

        // Check User does not have a pending subscription
        $user = Auth::user();
        if ($user->subscriptions()->where('stripe_status', 'past_due')->first()) {
            return $this->error([
                'message' => 'You already have a past due subscription. Please verify your details in the billing page,
                and contact us if the issue persists.',
            ]);
        }

        $checkoutBuilder = $user
            ->newSubscription($pricing, BillingHelper::getPricing($pricing)[$plan])
            ->allowPromotionCodes();

        // Disable trial for now
        // if ($trial != null) {
        //     $checkoutBuilder->trialUntil(now()->addDays(3)->addHour());
        // }

        $checkout = $checkoutBuilder
            ->collectTaxIds()
            ->checkout([
                'success_url' => front_url('/subscriptions/success'),
                'cancel_url' => front_url('/subscriptions/error'),
                'billing_address_collection' => 'required',
                'customer_update' => [
                    'address' => 'auto',
                    'name' => 'never',
                ],
            ]);

        return $this->success([
            'checkout_url' => $checkout->url,
        ]);
    }

    public function getUsersCount()
    {
        $this->middleware('auth');
        return [
            'count' => (new UserHelper(Auth::user()))->getActiveMembersCount() - 1,
        ];
    }

    public function updateStripeDetails(UpdateStripeDetailsRequest $request)
    {
        $user = Auth::user();
        if (!$user->hasStripeId()) {
            $user->createAsStripeCustomer([
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);
        }
        $user->updateStripeCustomer([
            'email' => $request->email,
            'name' => $request->name,
        ]);

        return $this->success([
            'message' => 'Details saved.',
        ]);
    }

    public function billingPortal()
    {
        $this->middleware('auth');
        if (!Auth::user()->has_customer_id) {
            return $this->error([
                'message' => 'Please subscribe before accessing your billing portal.',
            ]);
        }

        return $this->success([
            'portal_url' => Auth::user()->billingPortalUrl(front_url('/home')),
        ]);
    }
}
