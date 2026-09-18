<?php

namespace App\Service\Admin;

use App\Models\User;
use Laravel\Cashier\Cashier;
use Stripe\Invoice;

/** Stripe Basil uses invoice payments and plural subscription discounts. */
class AdminStripeState
{
    public function subscriptions(User $user): array
    {
        $subscriptions = Cashier::stripe()->subscriptions->all([
            'customer' => $user->stripe_id, 'limit' => 100, 'status' => 'all', 'expand' => ['data.discounts'],
        ]);
        abort_if($subscriptions->has_more, 422, 'Too many subscriptions for a safe billing snapshot.');
        return array_map(fn ($sub) => [
            'id' => $sub->id, 'status' => $sub->status, 'trial_end' => $sub->trial_end,
            'cancel_at_period_end' => $sub->cancel_at_period_end,
            'discounts' => array_values(array_filter(array_map(fn ($discount) => is_object($discount) ? $discount->coupon?->id : null, $sub->discounts ?? []))),
        ], $subscriptions->data);
    }

    public function refundablePayment(User $user, Invoice $invoice): array
    {
        abort_unless($invoice->customer === $user->stripe_id, 403);
        $payments = Cashier::stripe()->invoicePayments->all([
            'invoice' => $invoice->id, 'status' => 'paid', 'limit' => 2,
            'expand' => ['data.payment.payment_intent.latest_charge', 'data.payment.charge'],
        ]);
        // A payment can cover multiple invoices. Never refund another invoice's allocation.
        abort_if($payments->has_more || count($payments->data) !== 1, 422, 'A single paid invoice payment is required for this refund.');
        $payment = $payments->data[0];
        $source = $payment->payment;
        $charge = $source->type === 'payment_intent' ? $source->payment_intent?->latest_charge : ($source->type === 'charge' ? $source->charge : null);
        abort_unless(is_object($charge) && $charge->customer === $user->stripe_id && $charge->paid, 422, 'No refundable customer charge.');
        abort_unless($payment->amount_paid === $charge->amount && $payment->amount_paid === $invoice->amount_paid, 422, 'Split or shared invoice payments require manual refund review.');
        return ['charge_id' => $charge->id, 'amount_paid' => $payment->amount_paid,
            'amount_refunded' => $charge->amount_refunded, 'currency' => $charge->currency];
    }

    public function paymentRows(User $user): array
    {
        $invoices = $user->invoices(false, ['expand' => ['data.payments']]);
        $charges = Cashier::stripe()->charges->all(['customer' => $user->stripe_id, 'limit' => 100]);
        return $invoices->map(function ($invoice) use ($charges) {
            $payments = $invoice->payments;
            $matched = [];
            foreach ($payments?->data ?? [] as $payment) {
                $source = $payment->payment;
                foreach ($charges->data as $charge) {
                    if (($source->type === 'charge' && $source->charge === $charge->id)
                        || ($source->type === 'payment_intent' && $source->payment_intent === $charge->payment_intent)) {
                        $matched[] = $charge;
                    }
                }
            }
            $refunded = count($matched) > 0 && !($payments?->has_more ?? true)
                && count($matched) === count($payments->data)
                && collect($matched)->every(fn ($charge) => $charge->refunded);
            return ['id' => $invoice->id, 'amount_paid' => $invoice->amount_paid,
                'name' => ucfirst($invoice->account_name ?? ''),
                'creation_date' => \Carbon\Carbon::parse($invoice->created)->format('Y-m-d H:i:s'),
                'status' => $refunded ? 'refunded' : $invoice->status,
                'refund_status_known' => !($payments?->has_more ?? true) && count($matched) === count($payments->data ?? [])];
        })->all();
    }
}
