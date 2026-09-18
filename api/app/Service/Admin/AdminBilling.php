<?php

namespace App\Service\Admin;

use App\Models\User;
use Illuminate\Http\Request;

class AdminBilling
{
    use AdminResponses;

    public function getCustomer(User $user)
    {
        if (!$user->hasStripeId()) {
            return $this->error([
                "message" => "Stripe user not created",
            ]);
        }

        $stripeCustomer = $user->asStripeCustomer();

        return $this->success([
            'billing_email' => $stripeCustomer->email,
            'billing_name' => $stripeCustomer->name,
        ]);
    }

    public function updateCustomer(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'billing_email' => 'required|email',
            'billing_name' => 'required|string|max:255',
        ]);

        $user = User::findOrFail($request->get("user_id"));

        if (!$user->hasStripeId()) {
            return $this->error([
                "message" => "Stripe user not created",
            ]);
        }

        AdminOperations::log('Update billing customer', [
            'user_id' => $user->id,
            'stripe_id' => $user->stripe_id,
        ]);

        $updated = $user->updateStripeCustomer([
            'email' => $request->billing_email,
            'name' => $request->billing_name,
        ]);

        if ($request->attributes->has('admin_api_action_id')) {
            abort_unless($updated->email === $request->billing_email && $updated->name === $request->billing_name, 409, 'Customer update not confirmed.');
        }
        return $this->success(['message' => 'Billing info updated successfully']);
    }

    public function getSubscriptions(User $user)
    {
        if (!$user->hasStripeId()) {
            return $this->error([
                "message" => "Stripe user not created",
            ]);
        }
        $subscriptions = $user->subscriptions()->latest()->take(100)->get()->map(function ($subscription) use ($user) {
            return  [
                "id" => $subscription->id,
                "stripe_id" => $subscription->stripe_id,
                "name" => ucfirst($user->name),
                "plan" => $subscription->type,
                "status" => $subscription->stripe_status,
                "creation_date" => $subscription->created_at->format('Y-m-d'),
                "canceled_at" => $subscription->ends_at ? $subscription->ends_at->format('Y-m-d') : null,
            ];
        });
        return $this->success([
            'subscriptions'  =>  $subscriptions,
        ]);
    }

    public function getPayments(User $user)
    {
        if (!$user->hasStripeId()) {
            return $this->error([
                "message" => "Stripe user not created",
            ]);
        }
        $payments = app(AdminStripe::class)->paymentRows($user);
        return $this->success([
            'payments'  =>  $payments,
        ]);
    }
}
