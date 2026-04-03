<?php

namespace App\Services\Tax;

use Carbon\Carbon;
use Laravel\Cashier\Cashier;
use Stripe\Invoice;

class StripeExportDatasetService
{
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
        $stats = [
            'payment_not_successful_count' => 0,
            'refunded_invoices_count' => 0,
            'missing_data_invoices_count' => 0,
            'total_invoice' => 0,
            'processed_invoice_count' => 0,
            'defaulted_to_fr_count' => 0,
        ];

        $queryOptions = [
            'limit' => 100,
            'expand' => [
                'data.customer',
                'data.customer.address',
                'data.customer.tax_ids',
                'data.payment_intent',
                'data.payment_intent.payment_method',
                'data.payment_intent.latest_charge.balance_transaction',
                'data.charge.balance_transaction',
                'data.automatic_tax',
                'data.total_tax_amounts.tax_rate',
            ],
            'status' => 'paid',
            'created' => [
                'gte' => Carbon::parse($startDate)->startOfDay()->timestamp,
                'lte' => Carbon::parse($endDate)->endOfDay()->timestamp,
            ],
        ];

        $invoices = Cashier::stripe()->invoices->all($queryOptions);

        do {
            if (empty($invoices->data)) {
                break;
            }

            foreach ($invoices as $invoice) {
                $stats['total_invoice']++;

                $invoiceStatus = $invoice->status ?? null;
                $paymentIntentStatus = $invoice->payment_intent->status ?? null;

                if ($invoiceStatus !== 'paid' && $paymentIntentStatus !== 'succeeded') {
                    $stats['payment_not_successful_count']++;
                    continue;
                }

                $netInvoiceAmount = $this->getNetInvoiceAmount($invoice);
                if (($invoice->total ?? 0) > 0 && $netInvoiceAmount <= 0) {
                    $stats['refunded_invoices_count']++;
                    continue;
                }

                try {
                    $row = $this->formatDatasetRow($invoice);
                    if (($row['_defaulted_to_fr'] ?? false) === true) {
                        $stats['defaulted_to_fr_count']++;
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
        return [
            'invoice_id' => $row['invoice_id'],
            'created_at' => $row['created_at'],
            'cust_id' => $row['cust_id'],
            'cust_vat_id' => $row['cust_vat_id'],
            'cust_country' => $row['cust_country'],
            'tax_rate' => $row['tax_rate'],
            'customer_type' => $row['customer_type'],
            'total_usd' => $row['total_usd'],
            'tax_total_usd' => $row['tax_total_usd'],
            'total_after_tax_usd' => $row['total_after_tax_usd'],
            'total_eur' => $row['total_eur'],
            'tax_total_eur' => $row['tax_total_eur'],
            'total_after_tax_eur' => $row['total_after_tax_eur'],
            'stripe_fee_eur' => $row['stripe_fee_eur'],
        ];
    }

    private function formatDatasetRow(Invoice $invoice): array
    {
        [$country, $defaultedToFrance] = $this->resolveCountry($invoice);
        $vatId = $this->extractVatId($invoice);
        $cleanVatId = $vatId ? $this->cleanVatNumber($vatId) : null;
        $taxRate = $this->computeTaxRate($country, $cleanVatId);

        $caNetUsd = $this->getNetInvoiceAmount($invoice);
        $taxAmountCollectedUsd = $taxRate > 0 ? $caNetUsd * $taxRate / ($taxRate + 100) : 0;

        [$grossAmountEur, $stripeFeeEur] = $this->resolveGrossAmountAndFeeEur($invoice);
        $caNetEur = $this->applyInvoiceAdjustmentsToGrossAmount($invoice, $grossAmountEur);
        $taxAmountCollectedEur = $taxRate > 0 ? $caNetEur * $taxRate / ($taxRate + 100) : 0;

        $customerType = is_null($cleanVatId) && $this->isEuropeanCountry($country) ? 'individual' : 'business';
        $desEligible = $this->isEligibleForDes($country, $cleanVatId);
        $createdAt = Carbon::createFromTimestamp($invoice->created);

        return [
            'invoice_id' => $invoice->id,
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'created_ts' => $invoice->created,
            'cust_id' => $invoice->customer->id ?? 'unknown',
            'cust_vat_id' => $cleanVatId,
            'cust_country' => $country,
            'customer_type' => $customerType,
            'tax_rate' => $taxRate,
            'total_usd' => $caNetUsd / 100,
            'tax_total_usd' => $taxAmountCollectedUsd / 100,
            'total_after_tax_usd' => ($caNetUsd - $taxAmountCollectedUsd) / 100,
            'total_eur' => $caNetEur / 100,
            'tax_total_eur' => $taxAmountCollectedEur / 100,
            'total_after_tax_eur' => ($caNetEur - $taxAmountCollectedEur) / 100,
            'stripe_fee_eur' => $this->applyPartialInvoiceAdjustmentsToGrossAmount($invoice, $grossAmountEur, $stripeFeeEur) / 100,
            'des_eligible' => $desEligible,
            'des_country_code' => $country,
            'des_vat_number' => $cleanVatId,
            'des_amount_eur' => ($caNetEur - $taxAmountCollectedEur) / 100,
            '_defaulted_to_fr' => $defaultedToFrance,
        ];
    }

    private function resolveCountry(Invoice $invoice): array
    {
        if (!empty($invoice->customer->address->country)) {
            return [$invoice->customer->address->country, false];
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

        foreach (($invoice->customer->tax_ids->data ?? []) as $taxId) {
            if (!empty($taxId->country)) {
                return [$taxId->country, false];
            }
        }

        $paymentCountry = $invoice->payment_intent->payment_method->card->country ?? null;
        if (!empty($paymentCountry)) {
            return [$paymentCountry, false];
        }

        return ['FR', true];
    }

    private function resolveGrossAmountAndFeeEur(Invoice $invoice): array
    {
        if (isset($invoice->charge) && isset($invoice->charge->balance_transaction)) {
            $netEur = $invoice->charge->balance_transaction->amount ?? 0;
            $feeEur = $invoice->charge->balance_transaction->fee ?? 0;

            return [$netEur + $feeEur, $feeEur];
        }

        if (isset($invoice->payment_intent->latest_charge) && isset($invoice->payment_intent->latest_charge->balance_transaction)) {
            $netEur = $invoice->payment_intent->latest_charge->balance_transaction->amount ?? 0;
            $feeEur = $invoice->payment_intent->latest_charge->balance_transaction->fee ?? 0;

            return [$netEur + $feeEur, $feeEur];
        }

        foreach (($invoice->payment_intent->charges->data ?? []) as $charge) {
            if (isset($charge->balance_transaction)) {
                $netEur = $charge->balance_transaction->amount ?? 0;
                $feeEur = $charge->balance_transaction->fee ?? 0;

                return [$netEur + $feeEur, $feeEur];
            }
        }

        try {
            $charges = Cashier::stripe()->charges->all([
                'invoice' => $invoice->id,
                'limit' => 1,
                'expand' => ['data.balance_transaction'],
            ]);

            $charge = $charges->data[0] ?? null;
            if ($charge && isset($charge->balance_transaction)) {
                $netEur = $charge->balance_transaction->amount ?? 0;
                $feeEur = $charge->balance_transaction->fee ?? 0;

                return [$netEur + $feeEur, $feeEur];
            }
        } catch (\Throwable $e) {
        }

        if (($invoice->currency ?? null) === 'eur') {
            return [(int) ($invoice->total ?? 0), 0];
        }

        throw new \RuntimeException("Could not resolve EUR amount for invoice {$invoice->id}");
    }

    private function extractVatId(Invoice $invoice): ?string
    {
        foreach (($invoice->customer->tax_ids->data ?? []) as $taxId) {
            if (!empty($taxId->value)) {
                return $taxId->value;
            }
        }

        return null;
    }

    private function isEligibleForDes(?string $country, ?string $vatId): bool
    {
        if (!$country || $country === 'FR' || !$this->isEuropeanCountry($country) || !$vatId) {
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

    private function getInvoiceRefundAmount(Invoice $invoice): int
    {
        $invoiceRefundAmount = (int) ($invoice->amount_refunded ?? 0);
        $chargeRefundAmount = 0;

        if (isset($invoice->charge)) {
            $chargeRefundAmount = (int) ($invoice->charge->amount_refunded ?? 0);

            if ($chargeRefundAmount === 0 && isset($invoice->charge->refunded) && $invoice->charge->refunded) {
                $chargeRefundAmount = (int) ($invoice->total ?? 0);
            }
        }

        return max($invoiceRefundAmount, $chargeRefundAmount);
    }

    private function getInvoiceCreditNotesAmount(Invoice $invoice): int
    {
        return (int) (($invoice->post_payment_credit_notes_amount ?? 0) + ($invoice->pre_payment_credit_notes_amount ?? 0));
    }

    private function getNetInvoiceAmount(Invoice $invoice): int
    {
        return (int) (($invoice->total ?? 0) - $this->getInvoiceRefundAmount($invoice) - $this->getInvoiceCreditNotesAmount($invoice));
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
}
