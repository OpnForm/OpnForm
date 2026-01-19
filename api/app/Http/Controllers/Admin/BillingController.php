<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Cashier\Cashier;

class BillingController extends Controller
{
    public function __construct()
    {
        $this->middleware('moderator');
    }

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

        AdminController::log('Update billing customer', [
            'user_id' => $user->id,
            'stripe_id' => $user->stripe_id,
        ]);

        $user->updateStripeCustomer([
            'email' => $request->billing_email,
            'name' => $request->billing_name,
        ]);

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
        $payments = $user->invoices();
        $payments = $payments->map(function ($payment) use ($user) {
            $status = $payment->status;

            // If the underlying Stripe charge was refunded, reflect that in the status
            try {
                $stripeInvoice = Cashier::stripe()->invoices->retrieve($payment->id, ['expand' => ['charge']]);
                if (isset($stripeInvoice->charge) && ($stripeInvoice->charge->refunded ?? false)) {
                    $status = 'refunded';
                }
            } catch (\Exception $e) {
                // Ignore errors while deriving refund status; fall back to invoice status
            }

            return  [
                "id" => $payment->id,
                "amount_paid" => ($payment->amount_paid),
                "name" => ucfirst($payment->account_name),
                "creation_date" => Carbon::parse($payment->created)->format("Y-m-d H:i:s"),
                "status" => $status,
            ];
        });
        return $this->success([
            'payments'  =>  $payments,
        ]);
    }
}
