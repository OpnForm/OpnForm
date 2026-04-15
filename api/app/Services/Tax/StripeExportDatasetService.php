<?php

namespace App\Services\Tax;

use Carbon\Carbon;
use Laravel\Cashier\Cashier;
use Stripe\Invoice;

class StripeExportDatasetService
{
    private array $chargesByPaymentIntentId = [];

    public const EU_TAX_RATES = [
        'AT' => 20,
        'BE' => 21,
        'BG' => 20,
        'HR' => 25,
        'CY' => 19,
        'CZ' => 21,
        'DK' => 25,
        'EE' => 22,
        'FI' => 25.5,
        'FR' => 20,
        'DE' => 19,
        'GR' => 24,
        'HU' => 27,
        'IE' => 23,
        'IT' => 22,
        'LV' => 21,
        'LT' => 21,
        'LU' => 17,
        'MT' => 18,
        'NL' => 21,
        'PL' => 23,
        'PT' => 23,
        'RO' => 19,
        'SK' => 20,
        'SI' => 22,
        'ES' => 21,
        'SE' => 25,
    ];

    public function collect(string $startDate, string $endDate, ?callable $onInvoiceProcessed = null): array
    {
        $processedInvoices = [];
        $this->chargesByPaymentIntentId = $this->prefetchChargesByPaymentIntentId($startDate, $endDate);
        $stats = [
            'payment_not_successful_count' => 0,
            'refunded_invoices_count' => 0,
            'disputed_invoices_count' => 0,
            'missing_data_invoices_count' => 0,
            'total_invoice' => 0,
            'processed_invoice_count' => 0,
            'defaulted_to_fr_count' => 0,
        ];

        $queryOptions = [
            'limit' => 100,
            'expand' => [
                'data.status_transitions',
                'data.payments',
                'data.automatic_tax',
            ],
            'status' => 'paid',
            'created' => [
                'lte' => Carbon::parse($endDate)->endOfDay()->timestamp,
            ],
        ];

        $startTs = Carbon::parse($startDate)->startOfDay()->timestamp;
        $endTs = Carbon::parse($endDate)->endOfDay()->timestamp;

        $invoices = Cashier::stripe()->invoices->all($queryOptions);

        do {
            if (empty($invoices->data)) {
                break;
            }

            foreach ($invoices as $invoice) {
                $stats['total_invoice']++;

                $invoiceStatus = $invoice->status ?? null;
                if ($invoiceStatus !== 'paid') {
                    $stats['payment_not_successful_count']++;
                    continue;
                }

                $netInvoiceAmount = $this->getNetInvoiceAmount($invoice);
                if (($invoice->total ?? 0) > 0 && $netInvoiceAmount <= 0) {
                    $stats['refunded_invoices_count']++;
                }

                try {
                    $row = $this->formatDatasetRow($invoice);
                    if (($row['accounting_ts'] ?? 0) < $startTs || ($row['accounting_ts'] ?? 0) > $endTs) {
                        continue;
                    }
                    if (($row['_defaulted_to_fr'] ?? false) === true) {
                        $stats['defaulted_to_fr_count']++;
                    }
                    if (($row['dispute_amount_usd'] ?? 0) > 0) {
                        $stats['disputed_invoices_count']++;
                    }
                    $processedInvoices[] = $row;
                    $stats['processed_invoice_count']++;

                    if ($onInvoiceProcessed) {
                        $onInvoiceProcessed($stats, $row);
                    }
                } catch (\Throwable $e) {
                    $stats['missing_data_invoices_count']++;
                }
            }

            if (empty($invoices->data) || !$invoices->has_more) {
                break;
            }

            $queryOptions['starting_after'] = end($invoices->data)->id;
            $invoices = Cashier::stripe()->invoices->all($queryOptions);
        } while (true);

        return [
            'rows' => $processedInvoices,
            'stats' => $stats,
        ];
    }

    public function toTaxExportRow(array $row): array
    {
        $grossTotalEur = (float) ($row['gross_total_eur'] ?? $row['total_eur']);
        $stripeFeeEur = (float) ($row['stripe_fee_eur'] ?? 0);
        $totalEur = (float) ($row['total_eur'] ?? 0);

        return [
            'invoice_id' => $row['invoice_id'],
            'charge_id' => $row['charge_id'] ?? null,
            'payment_intent_id' => $row['payment_intent_id'] ?? null,
            'created_at' => $row['created_at'],
            'accounting_at' => $row['accounting_at'] ?? $row['created_at'],
            'cust_id' => $row['cust_id'],
            'cust_vat_id' => $row['cust_vat_id'],
            'cust_country' => $row['cust_country'],
            'tax_rate' => $row['tax_rate'],
            'customer_type' => $row['customer_type'],
            'gross_total_usd' => $row['gross_total_usd'] ?? $row['total_usd'],
            'refund_amount_usd' => $row['refund_amount_usd'] ?? 0,
            'credit_notes_amount_usd' => $row['credit_notes_amount_usd'] ?? 0,
            'chargeback_amount_usd' => $row['dispute_amount_usd'] ?? 0,
            'total_usd' => $row['total_usd'],
            'tax_total_usd' => $row['tax_total_usd'],
            'total_after_tax_usd' => $row['total_after_tax_usd'],
            'dispute_amount_usd' => $row['dispute_amount_usd'] ?? 0,
            'gross_total_eur' => $row['gross_total_eur'] ?? $row['total_eur'],
            'refund_amount_eur' => $row['refund_amount_eur'] ?? 0,
            'credit_notes_amount_eur' => $row['credit_notes_amount_eur'] ?? 0,
            'chargeback_amount_eur' => $row['dispute_amount_eur'] ?? 0,
            'cash_basis_before_adjustments_eur' => round($grossTotalEur - (float) ($row['refund_amount_eur'] ?? 0) - (float) ($row['dispute_amount_eur'] ?? 0), 2),
            'total_eur' => $row['total_eur'],
            'tax_total_eur' => $row['tax_total_eur'],
            'total_after_tax_eur' => $row['total_after_tax_eur'],
            'stripe_fee_eur' => $row['stripe_fee_eur'],
            'net_after_stripe_fees_eur' => round($totalEur - $stripeFeeEur, 2),
            'dispute_amount_eur' => $row['dispute_amount_eur'] ?? 0,
        ];
    }

    private function formatDatasetRow(Invoice $invoice): array
    {
        [$country, $defaultedToFrance] = $this->resolveCountry($invoice);
        $vatId = $this->extractVatId($invoice);
        $cleanVatId = $vatId ? $this->cleanVatNumber($vatId) : null;
        $taxRate = $this->computeTaxRate($country, $cleanVatId);

        $grossAmountUsd = (int) ($invoice->total ?? 0);
        $refundAmountUsd = $this->getInvoiceRefundAmount($invoice);
        $creditNotesAmountUsd = $this->getInvoiceCreditNotesAmount($invoice);
        $cashRefundAmountUsd = max(0, $refundAmountUsd - $creditNotesAmountUsd);
        $caNetUsd = $this->getNetInvoiceAmount($invoice);
        $originalTaxAmountUsd = $this->getInvoiceTaxAmount($invoice);
        $taxAmountCollectedUsd = $this->applyPartialInvoiceAdjustments($invoice, $originalTaxAmountUsd);

        [$grossAmountEur, $stripeFeeEur] = $this->resolveGrossAmountAndFeeEur($invoice);
        $refundAmountEur = $this->getInvoiceRefundAmountEur($invoice, $grossAmountEur);
        $creditNotesAmountEur = $this->applyPartialInvoiceAdjustmentsToGrossAmount($invoice, $grossAmountEur, $creditNotesAmountUsd);
        $disputeAmountEur = $this->getInvoiceDisputeAmountEur($invoice, $grossAmountEur);
        $caNetEur = (int) max(0, round($grossAmountEur - $refundAmountEur - $creditNotesAmountEur - $disputeAmountEur));
        $taxAmountCollectedEur = $this->applyPartialInvoiceAdjustmentsToGrossAmount($invoice, $grossAmountEur, $originalTaxAmountUsd);
        $disputeAmountUsd = $this->getInvoiceDisputeAmount($invoice);
        $effectiveStripeFeeEur = $this->getInvoiceEffectiveStripeFeeEur($invoice, $stripeFeeEur);

        $hasEuVatId = $this->hasEuVatId($invoice, $country, $cleanVatId);
        $customerType = !$hasEuVatId && $this->isEuropeanCountry($country) ? 'individual' : 'business';
        $desEligible = $this->isEligibleForDes($country, $cleanVatId, $hasEuVatId);
        $accountingTs = $this->resolveAccountingTimestamp($invoice);
        $createdAt = Carbon::createFromTimestamp($invoice->created);
        $accountingAt = Carbon::createFromTimestamp($accountingTs);
        $charge = $this->resolveInvoiceCharge($invoice);
        $paymentIntentId = $this->extractPaymentIntentId($invoice);

        return [
            'invoice_id' => $invoice->id,
            'charge_id' => $charge->id ?? null,
            'payment_intent_id' => $paymentIntentId,
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'created_ts' => $invoice->created,
            'accounting_at' => $accountingAt->format('Y-m-d H:i:s'),
            'accounting_ts' => $accountingTs,
            'cust_id' => $this->resolveCustomerId($invoice),
            'cust_vat_id' => $cleanVatId,
            'cust_country' => $country,
            'customer_type' => $customerType,
            'tax_rate' => $taxRate,
            'gross_total_usd' => $grossAmountUsd / 100,
            'refund_amount_usd' => $cashRefundAmountUsd / 100,
            'credit_notes_amount_usd' => $creditNotesAmountUsd / 100,
            'total_usd' => $caNetUsd / 100,
            'tax_total_usd' => $taxAmountCollectedUsd / 100,
            'total_after_tax_usd' => ($caNetUsd - $taxAmountCollectedUsd) / 100,
            'dispute_amount_usd' => $disputeAmountUsd / 100,
            'gross_total_eur' => $grossAmountEur / 100,
            'refund_amount_eur' => $refundAmountEur / 100,
            'credit_notes_amount_eur' => $creditNotesAmountEur / 100,
            'total_eur' => $caNetEur / 100,
            'tax_total_eur' => $taxAmountCollectedEur / 100,
            'total_after_tax_eur' => ($caNetEur - $taxAmountCollectedEur) / 100,
            'stripe_fee_eur' => $effectiveStripeFeeEur / 100,
            'dispute_amount_eur' => $disputeAmountEur / 100,
            'des_eligible' => $desEligible,
            'des_country_code' => $country,
            'des_vat_number' => $cleanVatId,
            'des_amount_eur' => ($caNetEur - $taxAmountCollectedEur) / 100,
            '_defaulted_to_fr' => $defaultedToFrance,
        ];
    }

    private function resolveAccountingTimestamp(Invoice $invoice): int
    {
        foreach (($invoice->payments->data ?? []) as $payment) {
            $paidAt = $payment->status_transitions->paid_at ?? null;
            if (is_numeric($paidAt) && (int) $paidAt > 0) {
                return (int) $paidAt;
            }
        }

        foreach ([
            $invoice->effective_at ?? null,
            $invoice->status_transitions->paid_at ?? null,
            $invoice->status_transitions->finalized_at ?? null,
            $invoice->created ?? null,
        ] as $timestamp) {
            if (is_numeric($timestamp) && (int) $timestamp > 0) {
                return (int) $timestamp;
            }
        }

        throw new \RuntimeException("Could not resolve accounting timestamp for invoice {$invoice->id}");
    }

    private function resolveCountry(Invoice $invoice): array
    {
        if (!empty($invoice->customer_address->country)) {
            return [$invoice->customer_address->country, false];
        }

        if (!empty($invoice->customer->address->country)) {
            return [$invoice->customer->address->country, false];
        }

        foreach (($invoice->total_taxes ?? []) as $taxAmount) {
            $taxRateCountry = $taxAmount->tax_rate_details->country ?? null;
            if (!empty($taxRateCountry)) {
                return [$taxRateCountry, false];
            }
        }

        foreach (($invoice->total_tax_amounts ?? []) as $taxAmount) {
            $taxRateCountry = $taxAmount->tax_rate->country ?? null;
            if (!empty($taxRateCountry)) {
                return [$taxRateCountry, false];
            }
        }

        $autoTaxCountry = $invoice->automatic_tax->tax_location->country ?? null;
        if (!empty($autoTaxCountry)) {
            return [$autoTaxCountry, false];
        }

        foreach ($this->getCustomerTaxIds($invoice) as $taxId) {
            if (!empty($taxId->country)) {
                return [$taxId->country, false];
            }
        }

        $charge = $this->resolveInvoiceCharge($invoice);
        foreach ([
            $charge->payment_method_details->card->country ?? null,
            $charge->billing_details->address->country ?? null,
        ] as $paymentCountry) {
            if (!empty($paymentCountry)) {
                return [$paymentCountry, false];
            }
        }

        return ['FR', true];
    }

    private function resolveGrossAmountAndFeeEur(Invoice $invoice): array
    {
        if ((int) ($invoice->total ?? 0) <= 0) {
            return [0, 0];
        }

        if (isset($invoice->charge) && isset($invoice->charge->balance_transaction)) {
            $chargeAmountEur = $invoice->charge->balance_transaction->amount ?? 0;
            $feeEur = $invoice->charge->balance_transaction->fee ?? 0;

            return [$chargeAmountEur, $feeEur];
        }

        $charge = $this->resolveInvoiceCharge($invoice);
        if ($charge && isset($charge->balance_transaction)) {
            $chargeAmountEur = $charge->balance_transaction->amount ?? 0;
            $feeEur = $charge->balance_transaction->fee ?? 0;

            return [$chargeAmountEur, $feeEur];
        }

        if (($invoice->currency ?? null) === 'eur') {
            return [(int) ($invoice->total ?? 0), 0];
        }

        throw new \RuntimeException("Could not resolve EUR amount for invoice {$invoice->id}");
    }

    private function getInvoiceRefundAmountEur(Invoice $invoice, int $grossAmountEur): int
    {
        $charge = $this->resolveInvoiceCharge($invoice);
        if (!$charge) {
            return $this->applyPartialInvoiceAdjustmentsToGrossAmount($invoice, $grossAmountEur, max(0, $this->getInvoiceRefundAmount($invoice) - $this->getInvoiceCreditNotesAmount($invoice)));
        }

        $refundAmountEur = 0;
        foreach (($charge->refunds->data ?? []) as $refund) {
            $balanceTransaction = $refund->balance_transaction ?? null;
            if (is_object($balanceTransaction) && isset($balanceTransaction->amount)) {
                $refundAmountEur += abs((int) $balanceTransaction->amount);
                continue;
            }

            if (($charge->amount ?? 0) > 0 && isset($refund->amount)) {
                $refundAmountEur += (int) round($grossAmountEur * (((int) $refund->amount) / ((int) $charge->amount)));
            }
        }

        if ($refundAmountEur > 0) {
            return $refundAmountEur;
        }

        return $this->applyPartialInvoiceAdjustmentsToGrossAmount($invoice, $grossAmountEur, max(0, $this->getInvoiceRefundAmount($invoice) - $this->getInvoiceCreditNotesAmount($invoice)));
    }

    private function getInvoiceEffectiveStripeFeeEur(Invoice $invoice, int $chargeFeeEur): int
    {
        $charge = $this->resolveInvoiceCharge($invoice);
        if (!$charge) {
            return $chargeFeeEur;
        }

        $effectiveFeeEur = $chargeFeeEur;
        foreach (($charge->refunds->data ?? []) as $refund) {
            $balanceTransaction = $refund->balance_transaction ?? null;
            if (is_object($balanceTransaction) && isset($balanceTransaction->fee)) {
                $effectiveFeeEur += (int) $balanceTransaction->fee;
            }
        }

        return $effectiveFeeEur;
    }

    private function getInvoiceDisputeAmountEur(Invoice $invoice, int $grossAmountEur): int
    {
        $charge = $this->resolveInvoiceCharge($invoice);
        $disputeAmount = $this->getInvoiceDisputeAmount($invoice);
        if (!$charge || $disputeAmount <= 0) {
            return 0;
        }

        if (($charge->amount ?? 0) > 0) {
            return (int) round($grossAmountEur * ($disputeAmount / ((int) $charge->amount)));
        }

        return $this->applyPartialInvoiceAdjustmentsToGrossAmount($invoice, $grossAmountEur, $disputeAmount);
    }

    private function extractVatId(Invoice $invoice): ?string
    {
        foreach ($this->getCustomerTaxIds($invoice) as $taxId) {
            if (($taxId->type ?? null) === 'eu_vat' && !empty($taxId->value)) {
                return $taxId->value;
            }
        }

        return null;
    }

    private function hasEuVatId(Invoice $invoice, ?string $country, ?string $vatId): bool
    {
        if (!$vatId || !$country) {
            return false;
        }

        foreach ($this->getCustomerTaxIds($invoice) as $taxId) {
            if (($taxId->type ?? null) !== 'eu_vat') {
                continue;
            }

            $cleaned = $this->cleanVatNumber((string) ($taxId->value ?? ''));
            if ($cleaned === $vatId && str_starts_with($cleaned, $country)) {
                return true;
            }
        }

        return false;
    }

    private function isEligibleForDes(?string $country, ?string $vatId, bool $hasEuVatId): bool
    {
        if (!$country || $country === 'FR' || !$this->isEuropeanCountry($country) || !$vatId || !$hasEuVatId) {
            return false;
        }

        return str_starts_with($vatId, $country);
    }

    private function cleanVatNumber(string $vatId): string
    {
        return strtoupper(str_replace(['.', '-', ' '], '', $vatId));
    }

    private function isEuropeanCountry(?string $countryCode): bool
    {
        return isset(self::EU_TAX_RATES[$countryCode]);
    }

    private function computeTaxRate(?string $countryCode, ?string $vatId): float|int
    {
        if ($countryCode === 'FR' || empty($countryCode)) {
            return 20;
        }

        if ($vatId) {
            return 0;
        }

        return self::EU_TAX_RATES[$countryCode] ?? 0;
    }

    private function getInvoiceTaxAmount(Invoice $invoice): int
    {
        if (isset($invoice->tax) && is_numeric($invoice->tax)) {
            return (int) $invoice->tax;
        }

        if (isset($invoice->total_excluding_tax) && is_numeric($invoice->total_excluding_tax) && isset($invoice->total)) {
            return max(0, (int) $invoice->total - (int) $invoice->total_excluding_tax);
        }

        $totalTaxes = $invoice->total_taxes ?? null;
        if (is_iterable($totalTaxes)) {
            $sum = 0;
            foreach ($totalTaxes as $taxAmount) {
                $sum += (int) ($taxAmount->amount ?? 0);
            }

            return $sum;
        }

        $totalTaxAmounts = $invoice->total_tax_amounts ?? null;
        if (is_iterable($totalTaxAmounts)) {
            $sum = 0;
            foreach ($totalTaxAmounts as $taxAmount) {
                $sum += (int) ($taxAmount->amount ?? 0);
            }

            return $sum;
        }

        return 0;
    }

    private function getInvoiceDisputeAmount(Invoice $invoice): int
    {
        $charge = $this->resolveInvoiceCharge($invoice);
        if (!$charge || !($charge->disputed ?? false)) {
            return 0;
        }

        $dispute = $charge->dispute ?? null;
        $status = $dispute->status ?? null;
        if ($status === 'won') {
            return 0;
        }

        $amount = (int) ($dispute->amount ?? 0);
        if ($amount > 0) {
            return $amount;
        }

        return (int) ($charge->amount ?? 0);
    }

    private function getInvoiceRefundAmount(Invoice $invoice): int
    {
        $invoiceRefundAmount = (int) ($invoice->amount_refunded ?? 0);
        $charge = $this->resolveInvoiceCharge($invoice);
        $chargeRefundAmount = (int) ($charge->amount_refunded ?? 0);

        if ($chargeRefundAmount === 0 && isset($charge->refunded) && $charge->refunded) {
            $chargeRefundAmount = (int) ($invoice->total ?? 0);
        }

        return max($invoiceRefundAmount, $chargeRefundAmount);
    }

    private function getInvoiceCreditNotesAmount(Invoice $invoice): int
    {
        return (int) (($invoice->post_payment_credit_notes_amount ?? 0) + ($invoice->pre_payment_credit_notes_amount ?? 0));
    }

    private function getNetInvoiceAmount(Invoice $invoice): int
    {
        return (int) max(0, ($invoice->total ?? 0) - $this->getInvoiceRefundAmount($invoice) - $this->getInvoiceCreditNotesAmount($invoice) - $this->getInvoiceDisputeAmount($invoice));
    }

    private function applyPartialInvoiceAdjustments(Invoice $invoice, int|float $partialAmount): int
    {
        $originalAmount = (int) ($invoice->total ?? 0);
        $netAmount = $this->getNetInvoiceAmount($invoice);

        if ($originalAmount === 0 || $partialAmount == 0.0) {
            return 0;
        }

        if ($netAmount === $originalAmount) {
            return (int) round($partialAmount);
        }

        return (int) round($partialAmount * ($netAmount / $originalAmount));
    }

    private function applyInvoiceAdjustmentsToGrossAmount(Invoice $invoice, int|float $grossAmount): int
    {
        $originalAmount = (int) ($invoice->total ?? 0);
        $netAmount = $this->getNetInvoiceAmount($invoice);

        if ($originalAmount === 0 || $grossAmount == 0.0 || $netAmount === $originalAmount) {
            return (int) round($grossAmount);
        }

        return (int) round($grossAmount * ($netAmount / $originalAmount));
    }

    private function applyPartialInvoiceAdjustmentsToGrossAmount(Invoice $invoice, int|float $grossAmount, int|float $partialAmount): int
    {
        $originalAmount = (int) ($invoice->total ?? 0);

        if ($originalAmount === 0 || $grossAmount == 0.0 || $partialAmount == 0.0) {
            return 0;
        }

        return (int) round($grossAmount * ($partialAmount / $originalAmount));
    }

    private function resolveInvoiceCharge(Invoice $invoice): ?object
    {
        if (isset($invoice->charge) && is_object($invoice->charge)) {
            return $invoice->charge;
        }

        if (isset($invoice->payment_intent->latest_charge) && is_object($invoice->payment_intent->latest_charge)) {
            return $invoice->payment_intent->latest_charge;
        }

        foreach (($invoice->payment_intent->charges->data ?? []) as $charge) {
            if (is_object($charge)) {
                return $charge;
            }
        }

        $paymentIntentId = $this->extractPaymentIntentId($invoice);

        return $paymentIntentId ? ($this->chargesByPaymentIntentId[$paymentIntentId] ?? null) : null;
    }

    private function prefetchChargesByPaymentIntentId(string $startDate, string $endDate): array
    {
        $queryOptions = [
            'limit' => 100,
            'created' => [
                'lte' => Carbon::parse($endDate)->endOfDay()->timestamp,
            ],
            'expand' => [
                'data.balance_transaction',
                'data.refunds.data.balance_transaction',
                'data.dispute',
            ],
        ];

        $chargesByPaymentIntentId = [];
        $charges = Cashier::stripe()->charges->all($queryOptions);

        do {
            foreach ($charges as $charge) {
                $paymentIntentId = $charge->payment_intent ?? null;
                if (!$paymentIntentId) {
                    continue;
                }

                $existingCharge = $chargesByPaymentIntentId[$paymentIntentId] ?? null;
                $candidateHasBalanceTransaction = isset($charge->balance_transaction) && is_object($charge->balance_transaction);
                $existingHasBalanceTransaction = isset($existingCharge->balance_transaction) && is_object($existingCharge->balance_transaction);

                if (!$existingCharge || ($candidateHasBalanceTransaction && !$existingHasBalanceTransaction)) {
                    $chargesByPaymentIntentId[$paymentIntentId] = $charge;
                }
            }

            if (empty($charges->data) || !$charges->has_more) {
                break;
            }

            $queryOptions['starting_after'] = end($charges->data)->id;
            $charges = Cashier::stripe()->charges->all($queryOptions);
        } while (true);

        return $chargesByPaymentIntentId;
    }

    private function extractPaymentIntentId(Invoice $invoice): ?string
    {
        foreach (($invoice->payments->data ?? []) as $payment) {
            $paymentIntentId = $payment->payment->payment_intent ?? null;
            if (!empty($paymentIntentId)) {
                return (string) $paymentIntentId;
            }
        }

        if (isset($invoice->payment_intent) && is_string($invoice->payment_intent)) {
            return $invoice->payment_intent;
        }

        if (isset($invoice->payment_intent->id)) {
            return $invoice->payment_intent->id;
        }

        return null;
    }

    private function getCustomerTaxIds(Invoice $invoice): array
    {
        $taxIds = $invoice->customer_tax_ids ?? ($invoice->customer->tax_ids->data ?? []);
        if (is_array($taxIds)) {
            return $taxIds;
        }

        if ($taxIds instanceof \Traversable) {
            return iterator_to_array($taxIds, false);
        }

        if (is_iterable($taxIds)) {
            $items = [];
            foreach ($taxIds as $taxId) {
                $items[] = $taxId;
            }

            return $items;
        }

        return [];
    }

    private function resolveCustomerId(Invoice $invoice): string
    {
        if (isset($invoice->customer) && is_string($invoice->customer) && $invoice->customer !== '') {
            return $invoice->customer;
        }

        if (isset($invoice->customer->id) && is_string($invoice->customer->id) && $invoice->customer->id !== '') {
            return $invoice->customer->id;
        }

        if (isset($invoice->customer_id) && is_string($invoice->customer_id) && $invoice->customer_id !== '') {
            return $invoice->customer_id;
        }

        return 'unknown';
    }
}
