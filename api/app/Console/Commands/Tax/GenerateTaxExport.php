<?php

namespace App\Console\Commands\Tax;

use App\Exports\Tax\ArrayExport;
use App\Services\Tax\StripeBalanceSummaryService;
use App\Services\Tax\StripeExportDatasetService;
use App\Services\Tax\StripeExportDatasetStore;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Stripe\Invoice;

class GenerateTaxExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:generate-stripe-export
                            {--start-date= : Start date (YYYY-MM-DD)}
                            {--end-date= : End date (YYYY-MM-DD)}
                            {--dataset= : Reuse a built dataset id}
                            {--full-month : Use the full month of the start date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute Stripe VAT per country';

    public const EU_TAX_RATES = [
        'AT' => 20,
        'BE' => 21,
        'BG' => 20,
        'HR' => 25,
        'CY' => 19,
        'CZ' => 21,
        'DK' => 25,
        'EE' => 22,
        "FI" => 25.5,
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

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(
        StripeExportDatasetService $collector,
        StripeExportDatasetStore $store,
        StripeBalanceSummaryService $balanceSummaryService
    ) {
        // Start the processing timer
        $startTime = microtime(true);

        // iterate through all Stripe invoices
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');

        // If no start date, use first day of previous month
        if (!$startDate) {
            $startDate = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
            if (!$this->confirm("No start date specified. Use {$startDate}?", true)) {
                return Command::FAILURE;
            }
        } elseif (!Carbon::createFromFormat('Y-m-d', $startDate)) {
            $this->error('Invalid start date format. Use YYYY-MM-DD.');
            return Command::FAILURE;
        }

        // If no end date, use end of the month from start date
        if (!$endDate) {
            $endDate = Carbon::parse($startDate)->endOfMonth()->format('Y-m-d');
            $this->info("Using end date: {$endDate}");
        } elseif (!Carbon::createFromFormat('Y-m-d', $endDate)) {
            $this->error('Invalid end date format. Use YYYY-MM-DD.');
            return Command::FAILURE;
        }

        $this->info('Start date: ' . $startDate);
        $this->info('End date: ' . $endDate);

        $datasetId = $this->option('dataset');
        if ($datasetId) {
            $datasetRows = $store->loadRows($datasetId);
            $stats = $store->readMetadata($datasetId);
        } else {
            $payload = $collector->collect($startDate, $endDate);
            $datasetRows = $payload['rows'];
            $stats = $payload['stats'];
        }

        $processedInvoices = array_map(fn (array $row) => $collector->toTaxExportRow($row), $datasetRows);

        $aggregatedReport = $this->aggregateReport($processedInvoices);
        $balanceSummary = $this->resolveBalanceSummary(
            $startDate,
            $endDate,
            $datasetId ? (string) $datasetId : null,
            $stats,
            $store,
            $balanceSummaryService
        );
        $aggregatedReport = $this->appendReconciliationRows($aggregatedReport, $balanceSummary);

        $filePath = 'opnform-tax-export-per-invoice_' . $startDate . '_' . $endDate . '.xlsx';
        $this->exportAsXlsx($processedInvoices, $filePath);

        $aggregatedReportFilePath = 'opnform-tax-export-aggregated_' . $startDate . '_' . $endDate . '.xlsx';
        $this->exportAsXlsx($aggregatedReport, $aggregatedReportFilePath);

        // Calculate processing time
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);

        // Display the results with improved statistics
        $this->info('Processing completed in ' . $executionTime . ' seconds');
        $this->info('Total invoices found: ' . ($stats['total_invoice'] ?? count($datasetRows)));
        $this->info('Processed invoices: ' . ($stats['processed_invoice_count'] ?? count($processedInvoices)));
        $this->info('Excluded invoices:');
        $this->info(' - Payment not successful: ' . ($stats['payment_not_successful_count'] ?? 0));
        $this->info(' - Refunded / fully credited: ' . ($stats['refunded_invoices_count'] ?? 0));
        $this->info(' - Disputed: ' . ($stats['disputed_invoices_count'] ?? 0));
        $this->info(' - Missing required data: ' . ($stats['missing_data_invoices_count'] ?? 0));
        $this->info(' - Defaulted to France: ' . ($stats['defaulted_to_fr_count'] ?? 0));

        // Display volume metrics
        $grossVolumeUsd = array_sum(array_column($processedInvoices, 'total_usd'));
        $netVolumeUsd = array_sum(array_column($processedInvoices, 'total_after_tax_usd'));
        $taxTotalUsd = array_sum(array_column($processedInvoices, 'tax_total_usd'));
        $grossVolumeEur = array_sum(array_column($processedInvoices, 'total_eur'));
        $netVolumeEur = array_sum(array_column($processedInvoices, 'total_after_tax_eur'));
        $taxTotalEur = array_sum(array_column($processedInvoices, 'tax_total_eur'));

        $this->line('');
        $this->info('Volume Metrics (USD):');
        $this->info(' - Gross volume: $' . number_format($grossVolumeUsd, 2));
        $this->info(' - Tax collected: $' . number_format($taxTotalUsd, 2));
        $this->info(' - Net volume: $' . number_format($netVolumeUsd, 2));

        $this->line('');
        $this->info('Volume Metrics (EUR):');
        $this->info(' - Gross volume: €' . number_format($grossVolumeEur, 2));
        $this->info(' - Tax collected: €' . number_format($taxTotalEur, 2));
        $this->info(' - Net volume: €' . number_format($netVolumeEur, 2));
        $this->line('');
        $this->comment('Note: EUR amounts are GROSS (before Stripe fees) to match Stripe Dashboard.');
        $this->comment('Calculated as: balance_transaction->amount (NET) + balance_transaction->fee = GROSS.');
        $this->line('');
        $this->info('Stripe balance reconciliation (EUR):');
        $this->info(' - Gross collected: €' . number_format($balanceSummary['cash_gross_collected_eur'], 2));
        $this->info(' - Refunds: €' . number_format($balanceSummary['cash_refunds_eur'], 2));
        $this->info(' - Chargebacks: €' . number_format($balanceSummary['cash_chargebacks_eur'], 2));
        $this->info(' - Stripe fees: €' . number_format($balanceSummary['cash_stripe_fees_eur'], 2));
        $this->info(' - Adjustments / disputes: €' . number_format($balanceSummary['cash_adjustments_eur'], 2));
        $this->info(' - Net movement: €' . number_format($balanceSummary['cash_net_movement_eur'], 2));
        $this->info(' - Payouts: €' . number_format($balanceSummary['payouts_eur'], 2));

        return Command::SUCCESS;
    }

    private function aggregateReport($invoices): array
    {
        // Sum invoices per country
        $aggregatedReport = [];
        foreach ($invoices as $invoice) {
            $country = $invoice['cust_country'];
            $customerType = $invoice['customer_type'] ?? (is_null($invoice['cust_vat_id']) && $this->isEuropeanCountry($country) ? 'individual' : 'business');
            if (! isset($aggregatedReport[$country])) {
                $defaultVal = [
                    'count' => 0,
                    'gross_total_usd' => 0,
                    'refund_amount_usd' => 0,
                    'credit_notes_amount_usd' => 0,
                    'chargeback_amount_usd' => 0,
                    'total_usd' => 0,
                    'tax_total_usd' => 0,
                    'total_after_tax_usd' => 0,
                    'dispute_amount_usd' => 0,
                    'gross_total_eur' => 0,
                    'refund_amount_eur' => 0,
                    'credit_notes_amount_eur' => 0,
                    'chargeback_amount_eur' => 0,
                    'cash_basis_before_adjustments_eur' => 0,
                    'total_eur' => 0,
                    'tax_total_eur' => 0,
                    'total_after_tax_eur' => 0,
                    'stripe_fee_eur' => 0,
                    'net_after_stripe_fees_eur' => 0,
                    'dispute_amount_eur' => 0,
                ];
                $aggregatedReport[$country] = [
                    'individual' => $defaultVal,
                    'business' => $defaultVal,
                ];
            }
            $aggregatedReport[$country][$customerType]['count']++;
            $aggregatedReport[$country][$customerType]['gross_total_usd'] = ($aggregatedReport[$country][$customerType]['gross_total_usd'] ?? 0) + ($invoice['gross_total_usd'] ?? 0);
            $aggregatedReport[$country][$customerType]['refund_amount_usd'] = ($aggregatedReport[$country][$customerType]['refund_amount_usd'] ?? 0) + ($invoice['refund_amount_usd'] ?? 0);
            $aggregatedReport[$country][$customerType]['credit_notes_amount_usd'] = ($aggregatedReport[$country][$customerType]['credit_notes_amount_usd'] ?? 0) + ($invoice['credit_notes_amount_usd'] ?? 0);
            $aggregatedReport[$country][$customerType]['chargeback_amount_usd'] = ($aggregatedReport[$country][$customerType]['chargeback_amount_usd'] ?? 0) + ($invoice['chargeback_amount_usd'] ?? 0);
            $aggregatedReport[$country][$customerType]['total_usd'] = ($aggregatedReport[$country][$customerType]['total_usd'] ?? 0) + $invoice['total_usd'];
            $aggregatedReport[$country][$customerType]['tax_total_usd'] = ($aggregatedReport[$country][$customerType]['tax_total_usd'] ?? 0) + $invoice['tax_total_usd'];
            $aggregatedReport[$country][$customerType]['total_after_tax_usd'] = ($aggregatedReport[$country][$customerType]['total_after_tax_usd'] ?? 0) + $invoice['total_after_tax_usd'];
            $aggregatedReport[$country][$customerType]['dispute_amount_usd'] = ($aggregatedReport[$country][$customerType]['dispute_amount_usd'] ?? 0) + ($invoice['dispute_amount_usd'] ?? 0);
            $aggregatedReport[$country][$customerType]['gross_total_eur'] = ($aggregatedReport[$country][$customerType]['gross_total_eur'] ?? 0) + ($invoice['gross_total_eur'] ?? 0);
            $aggregatedReport[$country][$customerType]['refund_amount_eur'] = ($aggregatedReport[$country][$customerType]['refund_amount_eur'] ?? 0) + ($invoice['refund_amount_eur'] ?? 0);
            $aggregatedReport[$country][$customerType]['credit_notes_amount_eur'] = ($aggregatedReport[$country][$customerType]['credit_notes_amount_eur'] ?? 0) + ($invoice['credit_notes_amount_eur'] ?? 0);
            $aggregatedReport[$country][$customerType]['chargeback_amount_eur'] = ($aggregatedReport[$country][$customerType]['chargeback_amount_eur'] ?? 0) + ($invoice['chargeback_amount_eur'] ?? 0);
            $aggregatedReport[$country][$customerType]['cash_basis_before_adjustments_eur'] = ($aggregatedReport[$country][$customerType]['cash_basis_before_adjustments_eur'] ?? 0) + ($invoice['cash_basis_before_adjustments_eur'] ?? 0);
            $aggregatedReport[$country][$customerType]['stripe_fee_eur'] = ($aggregatedReport[$country][$customerType]['stripe_fee_eur'] ?? 0) + $invoice['stripe_fee_eur'];
            $aggregatedReport[$country][$customerType]['total_eur'] = ($aggregatedReport[$country][$customerType]['total_eur'] ?? 0) + $invoice['total_eur'];
            $aggregatedReport[$country][$customerType]['tax_total_eur'] = ($aggregatedReport[$country][$customerType]['tax_total_eur'] ?? 0) + $invoice['tax_total_eur'];
            $aggregatedReport[$country][$customerType]['total_after_tax_eur'] = ($aggregatedReport[$country][$customerType]['total_after_tax_eur'] ?? 0) + $invoice['total_after_tax_eur'];
            $aggregatedReport[$country][$customerType]['net_after_stripe_fees_eur'] = ($aggregatedReport[$country][$customerType]['net_after_stripe_fees_eur'] ?? 0) + ($invoice['net_after_stripe_fees_eur'] ?? 0);
            $aggregatedReport[$country][$customerType]['dispute_amount_eur'] = ($aggregatedReport[$country][$customerType]['dispute_amount_eur'] ?? 0) + ($invoice['dispute_amount_eur'] ?? 0);
        }

        $finalReport = [];
        foreach ($aggregatedReport as $country => $data) {
            foreach ($data as $customerType => $aggData) {
                $finalReport[] = [
                    'country' => $country,
                    'customer_type' => $customerType,
                    ...$aggData,
                ];
            }
        }

        return $finalReport;
    }

    private function formatInvoice(Invoice $invoice): array
    {
        // Enhanced country detection logic with multiple fallbacks
        $country = null;
        $taxLocationFound = false;
        $defaultedToFrance = false;

        // Try to get country from customer's billing address
        if (isset($invoice->customer->address) && !empty($invoice->customer->address->country)) {
            $country = $invoice->customer->address->country;
            $taxLocationFound = true;
        }
        // Try to get country from payment method
        elseif (
            isset($invoice->payment_intent) && isset($invoice->payment_intent->payment_method) &&
            isset($invoice->payment_intent->payment_method->card) &&
            !empty($invoice->payment_intent->payment_method->card->country)
        ) {
            $country = $invoice->payment_intent->payment_method->card->country;
            $taxLocationFound = true;
        }
        // Try to get country from automatic tax calculation
        elseif (
            isset($invoice->automatic_tax) && isset($invoice->automatic_tax->tax_location) &&
            !empty($invoice->automatic_tax->tax_location->country)
        ) {
            $country = $invoice->automatic_tax->tax_location->country;
            $taxLocationFound = true;
        }
        // Try to get country from tax breakdown
        elseif (isset($invoice->total_tax_amounts) && !empty($invoice->total_tax_amounts->data)) {
            foreach ($invoice->total_tax_amounts->data as $taxAmount) {
                if (isset($taxAmount->tax_rate) && isset($taxAmount->tax_rate->country)) {
                    $country = $taxAmount->tax_rate->country;
                    $taxLocationFound = true;
                    break;
                }
            }
        }

        // Default to France if no country found
        if (!$taxLocationFound || is_null($country) || empty($country)) {
            $country = 'FR';
            $defaultedToFrance = true;
        }

        $vatId = null;
        if (isset($invoice->customer->tax_ids) && !empty($invoice->customer->tax_ids->data)) {
            $vatId = $invoice->customer->tax_ids->data[0]->value ?? null;
        }

        $taxRate = $this->computeTaxRate($country, $vatId);

        $grossAmountUsd = (int) ($invoice->total ?? 0);
        $refundAmountUsd = $this->getInvoiceRefundAmount($invoice);
        $creditNotesAmountUsd = $this->getInvoiceCreditNotesAmount($invoice);
        $caNetUsd = $this->getNetInvoiceAmount($invoice);
        $taxAmountCollectedUsd = $taxRate > 0 ? $caNetUsd * $taxRate / ($taxRate + 100) : 0;

        $grossAmountEur = 0;
        $stripeFeeEur = 0;
        if (isset($invoice->charge) && isset($invoice->charge->balance_transaction)) {
            // Fast path: invoice has embedded charge (OpnForm-style accounts)
            $bt = $invoice->charge->balance_transaction;
            // balance_transaction->amount is NET (after fees), add fees back to get GROSS
            $netEur = $bt->amount ?? 0;
            $feeEur = $bt->fee ?? 0;
            $grossAmountEur = $netEur + $feeEur; // GROSS = NET + fees
            $stripeFeeEur = $feeEur;
        } else {
            // Fallback: Stripe no longer embeds charge on invoice
            // Fetch the Charge explicitly by invoice ID, then read its balance_transaction
            try {
                $charges = Cashier::stripe()->charges->all([
                    'invoice' => $invoice->id,
                    'limit' => 1,
                    'expand' => ['data.balance_transaction'],
                ]);

                $charge = $charges->data[0] ?? null;

                if ($charge && isset($charge->balance_transaction)) {
                    $bt = $charge->balance_transaction;
                    // balance_transaction->amount is NET (after fees), add fees back to get GROSS
                    $netEur = $bt->amount ?? 0;
                    $feeEur = $bt->fee ?? 0;
                    $grossAmountEur = $netEur + $feeEur; // GROSS = NET + fees
                    $stripeFeeEur = $feeEur;
                }
            } catch (\Exception $e) {
                // Silently continue if charge retrieval fails - EUR will remain 0
            }
        }

        $caNetEur = $this->applyInvoiceAdjustmentsToGrossAmount($invoice, $grossAmountEur);
        $refundAmountEur = $this->applyPartialInvoiceAdjustmentsToGrossAmount($invoice, $grossAmountEur, $refundAmountUsd);
        $creditNotesAmountEur = $this->applyPartialInvoiceAdjustmentsToGrossAmount($invoice, $grossAmountEur, $creditNotesAmountUsd);
        $stripeFeeNetEur = $this->applyPartialInvoiceAdjustmentsToGrossAmount($invoice, $grossAmountEur, $stripeFeeEur);

        // Note: We calculate GROSS EUR (NET + fees) to match Stripe Dashboard
        // balance_transaction->amount = NET, balance_transaction->fee = Stripe fees

        $taxAmountCollectedEur = $taxRate > 0 ? $caNetEur * $taxRate / ($taxRate + 100) : 0;
        return [
            'invoice_id' => $invoice->id,
            'created_at' => Carbon::createFromTimestamp($invoice->created)->format('Y-m-d H:i:s'),
            'cust_id' => $invoice->customer->id ?? 'unknown',
            'cust_vat_id' => $vatId,
            'cust_country' => $country,
            'tax_rate' => $taxRate,
            'total_usd' => $caNetUsd / 100,
            'tax_total_usd' => $taxAmountCollectedUsd / 100,
            'total_after_tax_usd' => ($caNetUsd - $taxAmountCollectedUsd) / 100,
            'total_eur' => $caNetEur / 100,
            'tax_total_eur' => $taxAmountCollectedEur / 100,
            'total_after_tax_eur' => ($caNetEur - $taxAmountCollectedEur) / 100,
            'stripe_fee_eur' => $stripeFeeNetEur / 100,
            '_defaulted_to_fr' => $defaultedToFrance,
        ];
    }

    private function computeTaxRate($countryCode, $vatId)
    {
        // Since we're a French company, for France, always apply 20% VAT
        if (
            $countryCode == 'FR' ||
            is_null($countryCode) ||
            empty($countryCode)
        ) {
            return self::EU_TAX_RATES['FR'];
        }

        if ($taxRate = (self::EU_TAX_RATES[$countryCode] ?? null)) {
            // If VAT ID is provided, then TAX is 0%
            if (! $vatId) {
                return $taxRate;
            }
        }

        return 0;
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

    private function applyInvoiceAdjustmentsToGrossAmount(Invoice $invoice, int $grossAmount): int
    {
        $originalAmount = (int) ($invoice->total ?? 0);
        $netAmount = $this->getNetInvoiceAmount($invoice);

        if ($originalAmount === 0 || $grossAmount === 0 || $netAmount === $originalAmount) {
            return $grossAmount;
        }

        return (int) round($grossAmount * ($netAmount / $originalAmount));
    }

    private function applyPartialInvoiceAdjustmentsToGrossAmount(Invoice $invoice, int $grossAmount, int $partialAmount): int
    {
        $originalAmount = (int) ($invoice->total ?? 0);

        if ($originalAmount === 0 || $grossAmount === 0 || $partialAmount === 0) {
            return 0;
        }

        return (int) round($grossAmount * ($partialAmount / $originalAmount));
    }

    private function resolveBalanceSummary(
        string $startDate,
        string $endDate,
        ?string $datasetId,
        array $metadata,
        StripeExportDatasetStore $store,
        StripeBalanceSummaryService $balanceSummaryService
    ): array {
        if (!$datasetId) {
            return $balanceSummaryService->summarize($startDate, $endDate);
        }

        $existing = $metadata['balance_summary'] ?? null;
        if (is_array($existing) && !empty($existing)) {
            return $balanceSummaryService->aggregate([$existing]);
        }

        $chunkSummaries = [];
        foreach (($metadata['chunks'] ?? []) as $chunk) {
            $chunkSummary = $chunk['balance_summary'] ?? null;
            if (is_array($chunkSummary) && !empty($chunkSummary)) {
                $chunkSummaries[] = $chunkSummary;
            }
        }

        if (!empty($chunkSummaries)) {
            $summary = $balanceSummaryService->aggregate($chunkSummaries);
            $store->updateMetadata($datasetId, ['balance_summary' => $summary]);

            return $summary;
        }

        $this->warn('Dataset balance summary missing. Recomputing monthly reconciliation from Stripe...');

        $recomputedChunkSummaries = [];
        foreach (($metadata['chunks'] ?? []) as $chunk) {
            $chunkKey = (string) ($chunk['chunk_key'] ?? '');
            [$chunkStartDate, $chunkEndDate] = explode('_', $chunkKey) + [null, null];
            if (!$chunkStartDate || !$chunkEndDate) {
                continue;
            }

            $this->line("Reconciliation chunk {$chunkStartDate} -> {$chunkEndDate}");
            $recomputedChunkSummaries[] = $balanceSummaryService->summarize($chunkStartDate, $chunkEndDate);
        }

        if (!empty($recomputedChunkSummaries)) {
            $summary = $balanceSummaryService->aggregate($recomputedChunkSummaries);
            $store->updateMetadata($datasetId, ['balance_summary' => $summary]);

            return $summary;
        }

        return $balanceSummaryService->summarize($startDate, $endDate);
    }

    private function appendReconciliationRows(array $aggregatedReport, array $summary): array
    {
        foreach ([
            'cash_gross_collected' => 'cash_gross_collected_eur',
            'cash_refunds' => 'cash_refunds_eur',
            'cash_chargebacks' => 'cash_chargebacks_eur',
            'cash_stripe_fees' => 'cash_stripe_fees_eur',
            'cash_adjustments' => 'cash_adjustments_eur',
            'cash_net_movement' => 'cash_net_movement_eur',
            'payouts' => 'payouts_eur',
        ] as $label => $targetColumn) {
            $aggregatedReport[] = [
                'country' => '__RECONCILIATION__',
                'customer_type' => $label,
                'count' => 0,
                'gross_total_usd' => 0,
                'refund_amount_usd' => 0,
                'credit_notes_amount_usd' => 0,
                'chargeback_amount_usd' => 0,
                'total_usd' => 0,
                'tax_total_usd' => 0,
                'total_after_tax_usd' => 0,
                'gross_total_eur' => 0,
                'refund_amount_eur' => 0,
                'credit_notes_amount_eur' => 0,
                'chargeback_amount_eur' => 0,
                'cash_basis_before_adjustments_eur' => 0,
                'stripe_fee_eur' => 0,
                'net_after_stripe_fees_eur' => 0,
                'cash_gross_collected_eur' => 0,
                'cash_refunds_eur' => 0,
                'cash_chargebacks_eur' => 0,
                'cash_stripe_fees_eur' => 0,
                'cash_adjustments_eur' => 0,
                'cash_net_movement_eur' => 0,
                'payouts_eur' => 0,
                'total_eur' => 0,
                'tax_total_eur' => 0,
                'total_after_tax_eur' => 0,
                $targetColumn => $summary[$targetColumn],
            ];
        }

        return $aggregatedReport;
    }

    private function isEuropeanCountry($countryCode)
    {
        return isset(self::EU_TAX_RATES[$countryCode]);
    }

    private function exportAsXlsx($data, $filename)
    {
        if (count($data) == 0) {
            $this->info('Empty data. No file generated.');

            return;
        }

        (new ArrayExport($data))->store($filename, 'local', \Maatwebsite\Excel\Excel::XLSX);
        $this->line('File generated: ' . storage_path('app/' . $filename));
    }
}
