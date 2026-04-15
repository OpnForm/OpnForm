<?php

namespace App\Services\Tax;

use Carbon\Carbon;
use Laravel\Cashier\Cashier;

class StripeBalanceSummaryService
{
    public function summarize(string $startDate, string $endDate): array
    {
        $queryOptions = [
            'limit' => 100,
            'expand' => ['data.source'],
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

        $summary = $this->emptySummary();

        foreach ($rows as $transaction) {
            $type = $transaction->type ?? '';
            $amount = (int) ($transaction->amount ?? 0);
            $fee = (int) ($transaction->fee ?? 0);
            $net = (int) ($transaction->net ?? 0);

            if (in_array($type, ['charge', 'payment'], true)) {
                $summary['cash_gross_collected_eur'] += max(0, $amount) / 100;
                $summary['cash_stripe_fees_eur'] += $fee / 100;
                $summary['cash_net_movement_eur'] += $net / 100;
                continue;
            }

            if (in_array($type, ['refund', 'payment_refund'], true)) {
                $summary['cash_refunds_eur'] += abs($amount) / 100;
                $summary['cash_stripe_fees_eur'] += $fee / 100;
                $summary['cash_net_movement_eur'] += $net / 100;
                continue;
            }

            if ($this->isChargebackTransaction($transaction)) {
                $summary['cash_chargebacks_eur'] += abs($amount) / 100;
                $summary['cash_stripe_fees_eur'] += $fee / 100;
                $summary['cash_net_movement_eur'] += $net / 100;
                continue;
            }

            if ($type === 'stripe_fee') {
                $summary['cash_stripe_fees_eur'] += abs($amount) / 100;
                $summary['cash_net_movement_eur'] += $net / 100;
                continue;
            }

            if ($type === 'adjustment') {
                $summary['cash_adjustments_eur'] += $net / 100;
                $summary['cash_stripe_fees_eur'] += $fee / 100;
                $summary['cash_net_movement_eur'] += $net / 100;
                continue;
            }

            if ($type === 'payout') {
                $summary['payouts_eur'] += abs($amount) / 100;
            }
        }

        return $summary;
    }

    public function aggregate(array $summaries): array
    {
        $aggregate = $this->emptySummary();

        foreach ($summaries as $summary) {
            foreach ($aggregate as $key => $value) {
                $aggregate[$key] += (float) ($summary[$key] ?? 0);
            }
        }

        return $aggregate;
    }

    public function emptySummary(): array
    {
        return [
            'cash_gross_collected_eur' => 0.0,
            'cash_refunds_eur' => 0.0,
            'cash_chargebacks_eur' => 0.0,
            'cash_stripe_fees_eur' => 0.0,
            'cash_adjustments_eur' => 0.0,
            'cash_net_movement_eur' => 0.0,
            'payouts_eur' => 0.0,
        ];
    }

    private function isChargebackTransaction(object $transaction): bool
    {
        $type = $transaction->type ?? '';
        if (in_array($type, ['issuing_dispute', 'payment_reversal'], true)) {
            return true;
        }

        $source = $transaction->source ?? null;
        if (($source->object ?? null) === 'dispute') {
            return true;
        }

        $description = strtolower((string) ($transaction->description ?? ''));

        return $type === 'adjustment' && (str_contains($description, 'dispute') || str_contains($description, 'chargeback'));
    }
}
