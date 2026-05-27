<?php

namespace App\Services\Tax;

use Carbon\Carbon;
use Laravel\Cashier\Cashier;

class StripeCashReconciliationService
{
    public function buildExportRows(string $startDate, string $endDate, array $invoiceIndex = []): array
    {
        $transactions = $this->fetchTransactions($startDate, $endDate);
        $summary = [];
        $detailRows = [];

        foreach ($transactions as $transaction) {
            $detailRow = $this->mapTransaction($transaction, $invoiceIndex);
            $detailRows[] = $detailRow;

            $bucket = $detailRow['bucket'];
            if (!isset($summary[$bucket])) {
                $summary[$bucket] = [
                    'label' => $detailRow['category'],
                    'transaction_count' => 0,
                    'amount_abs_eur' => 0.0,
                    'fee_eur' => 0.0,
                    'net_eur' => 0.0,
                ];
            }

            $summary[$bucket]['transaction_count']++;
            $summary[$bucket]['amount_abs_eur'] += (float) ($detailRow['amount_abs_eur'] ?? 0);
            $summary[$bucket]['fee_eur'] += (float) ($detailRow['fee_eur'] ?? 0);
            $summary[$bucket]['net_eur'] += (float) ($detailRow['net_eur'] ?? 0);
        }

        $rows = [];
        foreach ([
            'charges',
            'refunds',
            'chargebacks',
            'additional_fees',
            'reserve_holds',
            'reserve_releases',
            'adjustments',
            'payouts',
            'other',
        ] as $bucket) {
            if (!isset($summary[$bucket])) {
                continue;
            }

            $rows[] = [
                'section' => 'summary',
                'category' => $summary[$bucket]['label'],
                'bucket' => $bucket,
                'transaction_count' => $summary[$bucket]['transaction_count'],
                'amount_abs_eur' => round($summary[$bucket]['amount_abs_eur'], 2),
                'fee_eur' => round($summary[$bucket]['fee_eur'], 2),
                'net_eur' => round($summary[$bucket]['net_eur'], 2),
            ];
        }

        $rows[] = [
            'section' => 'summary',
            'category' => 'Net activity (excluding payouts)',
            'bucket' => 'net_activity',
            'transaction_count' => '',
            'amount_abs_eur' => '',
            'fee_eur' => '',
            'net_eur' => round(array_sum(array_map(
                fn (array $row) => $row['bucket'] === 'payouts' ? 0 : (float) ($row['net_eur'] ?? 0),
                $detailRows
            )), 2),
        ];

        $rows[] = [];

        foreach ($detailRows as $detailRow) {
            $rows[] = $detailRow;
        }

        return $rows;
    }

    private function fetchTransactions(string $startDate, string $endDate): array
    {
        $queryOptions = [
            'limit' => 100,
            'created' => [
                'gte' => Carbon::parse($startDate)->startOfDay()->timestamp,
                'lte' => Carbon::parse($endDate)->endOfDay()->timestamp,
            ],
        ];

        $rows = [];
        $transactions = Cashier::stripe()->balanceTransactions->all($queryOptions);

        do {
            foreach ($transactions as $transaction) {
                if (($transaction->currency ?? null) !== 'eur') {
                    continue;
                }

                $rows[] = $transaction;
            }

            if (empty($transactions->data) || !$transactions->has_more) {
                break;
            }

            $queryOptions['starting_after'] = end($transactions->data)->id;
            $transactions = Cashier::stripe()->balanceTransactions->all($queryOptions);
        } while (true);

        return $rows;
    }

    private function mapTransaction(object $transaction, array $invoiceIndex): array
    {
        [$bucket, $label] = $this->classifyTransaction($transaction);

        $source = $transaction->source ?? null;
        $sourceObject = is_object($source) ? ($source->object ?? null) : null;
        $sourceId = is_string($source) ? $source : ($source->id ?? null);
        $chargeId = null;
        $invoiceId = null;
        $paymentIntentId = null;

        if ($sourceObject === 'charge') {
            $chargeId = $source->id ?? null;
            $invoiceId = $source->invoice ?? null;
            $paymentIntentId = $source->payment_intent ?? null;
        } elseif ($sourceObject === 'refund') {
            $chargeId = $source->charge ?? null;
            $paymentIntentId = $source->payment_intent ?? null;
        } elseif ($sourceObject === 'dispute') {
            $chargeId = $source->charge ?? null;
            $paymentIntentId = $source->payment_intent ?? null;
        } elseif ($sourceObject === 'payout') {
            $sourceId = $source->id ?? $sourceId;
        }

        if (!$chargeId && in_array($transaction->type ?? '', ['charge', 'payment'], true) && is_string($sourceId)) {
            $chargeId = $sourceId;
        }

        $invoiceRow = $this->resolveInvoiceRow($invoiceIndex, $invoiceId, $chargeId, $paymentIntentId);
        $amount = (int) ($transaction->amount ?? 0) / 100;
        $fee = (int) ($transaction->fee ?? 0) / 100;
        $net = (int) ($transaction->net ?? 0) / 100;

        return [
            'section' => 'transaction',
            'category' => $label,
            'bucket' => $bucket,
            'created_at' => Carbon::createFromTimestamp((int) $transaction->created)->format('Y-m-d H:i:s'),
            'available_on' => isset($transaction->available_on)
                ? Carbon::createFromTimestamp((int) $transaction->available_on)->format('Y-m-d')
                : null,
            'transaction_id' => $transaction->id ?? null,
            'transaction_type' => $transaction->type ?? null,
            'reporting_category' => $transaction->reporting_category ?? null,
            'description' => $transaction->description ?? null,
            'source_object' => $sourceObject,
            'source_id' => $sourceId,
            'charge_id' => $chargeId,
            'payment_intent_id' => $paymentIntentId,
            'invoice_id' => $invoiceId,
            'amount_eur' => round($amount, 2),
            'amount_abs_eur' => round(abs($amount), 2),
            'fee_eur' => round($fee, 2),
            'net_eur' => round($net, 2),
            'invoice_accounting_at' => $invoiceRow['accounting_at'] ?? null,
            'invoice_created_at' => $invoiceRow['created_at'] ?? null,
            'invoice_country' => $invoiceRow['cust_country'] ?? null,
            'invoice_customer_type' => $invoiceRow['customer_type'] ?? null,
            'invoice_gross_total_eur' => $invoiceRow['gross_total_eur'] ?? null,
            'invoice_total_eur' => $invoiceRow['total_eur'] ?? null,
            'invoice_stripe_fee_eur' => $invoiceRow['stripe_fee_eur'] ?? null,
        ];
    }

    private function resolveInvoiceRow(array $invoiceIndex, ?string $invoiceId, ?string $chargeId, ?string $paymentIntentId): ?array
    {
        if ($invoiceId && isset($invoiceIndex['by_invoice_id'][$invoiceId])) {
            return $invoiceIndex['by_invoice_id'][$invoiceId];
        }

        if ($chargeId && isset($invoiceIndex['by_charge_id'][$chargeId])) {
            return $invoiceIndex['by_charge_id'][$chargeId];
        }

        if ($paymentIntentId && isset($invoiceIndex['by_payment_intent_id'][$paymentIntentId])) {
            return $invoiceIndex['by_payment_intent_id'][$paymentIntentId];
        }

        return null;
    }

    private function classifyTransaction(object $transaction): array
    {
        $type = $transaction->type ?? '';
        $description = strtolower((string) ($transaction->description ?? ''));

        if (in_array($type, ['charge', 'payment'], true)) {
            return ['charges', 'Charges'];
        }

        if (in_array($type, ['refund', 'payment_refund'], true)) {
            return ['refunds', 'Refunds'];
        }

        if ($this->isChargebackTransaction($transaction)) {
            return ['chargebacks', 'Chargebacks'];
        }

        if ($type === 'stripe_fee') {
            return ['additional_fees', 'Additional Stripe fees'];
        }

        if ($type === 'payout_minimum_balance_hold') {
            return ['reserve_holds', 'Payout minimum balance hold'];
        }

        if ($type === 'payout_minimum_balance_release') {
            return ['reserve_releases', 'Payout minimum balance release'];
        }

        if ($type === 'adjustment' && str_contains($description, 'payout minimum balance hold')) {
            return ['reserve_holds', 'Payout minimum balance hold'];
        }

        if ($type === 'adjustment' && str_contains($description, 'payout minimum balance release')) {
            return ['reserve_releases', 'Payout minimum balance release'];
        }

        if ($type === 'adjustment') {
            return ['adjustments', 'Adjustments'];
        }

        if ($type === 'payout') {
            return ['payouts', 'Payouts'];
        }

        return ['other', 'Other'];
    }

    private function isChargebackTransaction(object $transaction): bool
    {
        $type = $transaction->type ?? '';
        if (in_array($type, ['issuing_dispute', 'payment_reversal'], true)) {
            return true;
        }

        $source = $transaction->source ?? null;
        if (is_object($source) && ($source->object ?? null) === 'dispute') {
            return true;
        }

        $description = strtolower((string) ($transaction->description ?? ''));

        return $type === 'adjustment' && (str_contains($description, 'dispute') || str_contains($description, 'chargeback'));
    }
}
