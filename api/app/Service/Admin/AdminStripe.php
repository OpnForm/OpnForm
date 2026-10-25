<?php

namespace App\Service\Admin;

use App\Models\User;
use Laravel\Cashier\Cashier;
use Stripe\Invoice;

/** Stripe Basil uses invoice payments and plural subscription discounts. */
class AdminStripe
{
    public static function withTimeout(callable $callback): mixed
    {
        $client = \Stripe\ApiRequestor::httpClient();
        $retries = \Stripe\Stripe::getMaxNetworkRetries();
        $timeout = $client instanceof \Stripe\HttpClient\CurlClient ? $client->getTimeout() : null;
        $connect = $client instanceof \Stripe\HttpClient\CurlClient ? $client->getConnectTimeout() : null;
        try {
            if ($timeout !== null) {
                $client->setTimeout(4);
                $client->setConnectTimeout(2);
            }
            \Stripe\Stripe::setMaxNetworkRetries(0);
            return $callback();
        } finally {
            if ($timeout !== null) {
                $client->setTimeout($timeout);
                $client->setConnectTimeout($connect);
            }
            \Stripe\Stripe::setMaxNetworkRetries($retries);
        }
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
            $single = count($matched) === 1 && count($payments->data ?? []) === 1 && !($payments?->has_more ?? true) && $matched[0]->amount === $invoice->amount_paid;
            return ['currency' => $invoice->currency, 'refundable_amount' => $single ? max(0, $matched[0]->amount - $matched[0]->amount_refunded) : null, 'id' => $invoice->id, 'amount_paid' => $invoice->amount_paid,
                'name' => ucfirst($invoice->account_name ?? ''),
                'creation_date' => \Carbon\Carbon::parse($invoice->created)->format('Y-m-d H:i:s'),
                'status' => $refunded ? 'refunded' : $invoice->status,
                'refund_status_known' => !($payments?->has_more ?? true) && count($matched) === count($payments->data ?? [])];
        })->all();
    }
}
