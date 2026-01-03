<?php

namespace App\Console\Commands\Tax;

use App\Exports\Tax\ArrayExport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Laravel\Cashier\Cashier;
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
    public function handle()
    {
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

        $processedInvoices = [];

        // Create a progress bar
        $queryOptions = [
            'limit' => 100, // Maximum allowed by Stripe API
            'expand' => [
                'data.customer',
                'data.customer.address',
                'data.customer.tax_ids',
                'data.payment_intent',
                'data.payment_intent.payment_method',
                'data.charge.balance_transaction',
                'data.automatic_tax',
                'data.total_tax_amounts'
            ],
            'status' => 'paid',
        ];
        if ($startDate) {
            $queryOptions['created']['gte'] = Carbon::parse($startDate)->startOfDay()->timestamp;
        }
        if ($endDate) {
            $queryOptions['created']['lte'] = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay()->timestamp;
        }

        $this->info('Fetching invoices from Stripe...');
        $invoices = Cashier::stripe()->invoices->all($queryOptions);
        $totalResults = $invoices->count();
        $this->info("Initial batch contains {$totalResults} invoices");

        $bar = $this->output->createProgressBar();
        $bar->start();

        // Improved counters for better tracking
        $paymentNotSuccessfulCount = 0;
        $refundedInvoicesCount = 0;
        $missingDataInvoicesCount = 0;
        $totalInvoice = 0;
        $processedInvoiceCount = 0;
        $defaultedToFranceCount = 0;

        // Volume metrics
        $grossVolumeUsd = 0;
        $netVolumeUsd = 0;
        $taxTotalUsd = 0;
        $grossVolumeEur = 0;
        $netVolumeEur = 0;
        $taxTotalEur = 0;

        do {
            if (empty($invoices->data)) {
                $this->line('No invoices found in this batch.');
                break;
            }

            foreach ($invoices as $invoice) {
                $totalInvoice++;

                // Check for payment status
                // Since we query with status='paid', check invoice status first
                // payment_intent->status may not always be available/expanded
                $invoiceStatus = $invoice->status ?? null;
                $paymentIntentStatus = $invoice->payment_intent->status ?? null;

                // Accept if invoice is paid OR payment_intent succeeded
                if ($invoiceStatus !== 'paid' && $paymentIntentStatus !== 'succeeded') {
                    $paymentNotSuccessfulCount++;
                    continue;
                }

                // Check if invoice was refunded
                if (isset($invoice->charge) && isset($invoice->charge->refunded) && $invoice->charge->refunded) {
                    $refundedInvoicesCount++;
                    continue;
                }

                try {
                    $formattedInvoice = $this->formatInvoice($invoice);

                    // Check if we defaulted to France
                    if ($formattedInvoice['cust_country'] === 'FR' && $formattedInvoice['_defaulted_to_fr'] === true) {
                        $defaultedToFranceCount++;
                    }

                    // Remove the internal tracking field
                    unset($formattedInvoice['_defaulted_to_fr']);

                    $processedInvoices[] = $formattedInvoice;
                    $processedInvoiceCount++;

                    // Track volume metrics
                    $grossVolumeUsd += $formattedInvoice['total_usd'];
                    $netVolumeUsd += $formattedInvoice['total_after_tax_usd'];
                    $taxTotalUsd += $formattedInvoice['tax_total_usd'];
                    $grossVolumeEur += $formattedInvoice['total_eur'];
                    $netVolumeEur += $formattedInvoice['total_after_tax_eur'];
                    $taxTotalEur += $formattedInvoice['tax_total_eur'];
                } catch (\Exception $e) {
                    $this->warn("Error processing invoice {$invoice->id}: {$e->getMessage()}");
                    $missingDataInvoicesCount++;
                    continue;
                }

                // Advance the progress bar
                $bar->advance();
            }

            // Safe pagination
            try {
                $lastInvoice = end($invoices->data);
                if ($lastInvoice) {
                    $queryOptions['starting_after'] = $lastInvoice->id;
                } else {
                    break;
                }

                // No need for sleep - Stripe API can handle the request rate
                $invoices = Cashier::stripe()->invoices->all($queryOptions);
                $batchSize = count($invoices->data);
                $totalResults += $batchSize;
            } catch (\Exception $e) {
                $this->error("Error fetching next batch of invoices: {$e->getMessage()}");
                break;
            }
        } while ($invoices->has_more);

        $bar->finish();
        $this->line('');

        $aggregatedReport = $this->aggregateReport($processedInvoices);

        $filePath = 'opnform-tax-export-per-invoice_' . $startDate . '_' . $endDate . '.xlsx';
        $this->exportAsXlsx($processedInvoices, $filePath);

        $aggregatedReportFilePath = 'opnform-tax-export-aggregated_' . $startDate . '_' . $endDate . '.xlsx';
        $this->exportAsXlsx($aggregatedReport, $aggregatedReportFilePath);

        // Calculate processing time
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);

        // Display the results with improved statistics
        $this->info('Processing completed in ' . $executionTime . ' seconds');
        $this->info('Total invoices found: ' . $totalInvoice);
        $this->info('Processed invoices: ' . $processedInvoiceCount);
        $this->info('Excluded invoices:');
        $this->info(' - Payment not successful: ' . $paymentNotSuccessfulCount);
        $this->info(' - Refunded: ' . $refundedInvoicesCount);
        $this->info(' - Missing required data: ' . $missingDataInvoicesCount);
        $this->info(' - Defaulted to France: ' . $defaultedToFranceCount);

        // Display volume metrics
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

        return Command::SUCCESS;
    }

    private function aggregateReport($invoices): array
    {
        // Sum invoices per country
        $aggregatedReport = [];
        foreach ($invoices as $invoice) {
            $country = $invoice['cust_country'];
            $customerType = is_null($invoice['cust_vat_id']) && $this->isEuropeanCountry($country) ? 'individual' : 'business';
            if (! isset($aggregatedReport[$country])) {
                $defaultVal = [
                    'count' => 0,
                    'total_usd' => 0,
                    'tax_total_usd' => 0,
                    'total_after_tax_usd' => 0,
                    'total_eur' => 0,
                    'tax_total_eur' => 0,
                    'total_after_tax_eur' => 0,
                ];
                $aggregatedReport[$country] = [
                    'individual' => $defaultVal,
                    'business' => $defaultVal,
                ];
            }
            $aggregatedReport[$country][$customerType]['count']++;
            $aggregatedReport[$country][$customerType]['total_usd'] = ($aggregatedReport[$country][$customerType]['total_usd'] ?? 0) + $invoice['total_usd'];
            $aggregatedReport[$country][$customerType]['tax_total_usd'] = ($aggregatedReport[$country][$customerType]['tax_total_usd'] ?? 0) + $invoice['tax_total_usd'];
            $aggregatedReport[$country][$customerType]['total_after_tax_usd'] = ($aggregatedReport[$country][$customerType]['total_after_tax_usd'] ?? 0) + $invoice['total_after_tax_usd'];
            $aggregatedReport[$country][$customerType]['total_eur'] = ($aggregatedReport[$country][$customerType]['total_eur'] ?? 0) + $invoice['total_eur'];
            $aggregatedReport[$country][$customerType]['tax_total_eur'] = ($aggregatedReport[$country][$customerType]['tax_total_eur'] ?? 0) + $invoice['tax_total_eur'];
            $aggregatedReport[$country][$customerType]['total_after_tax_eur'] = ($aggregatedReport[$country][$customerType]['total_after_tax_eur'] ?? 0) + $invoice['total_after_tax_eur'];
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

        // Safely calculate tax amounts
        $total = $invoice->total ?? 0;
        $taxAmountCollectedUsd = $taxRate > 0 ? $total * $taxRate / ($taxRate + 100) : 0;

        $totalEur = 0;
        if (isset($invoice->charge) && isset($invoice->charge->balance_transaction)) {
            // Fast path: invoice has embedded charge (OpnForm-style accounts)
            $bt = $invoice->charge->balance_transaction;
            // balance_transaction->amount is NET (after fees), add fees back to get GROSS
            $netEur = $bt->amount ?? 0;
            $feeEur = $bt->fee ?? 0;
            $totalEur = $netEur + $feeEur; // GROSS = NET + fees
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
                    $totalEur = $netEur + $feeEur; // GROSS = NET + fees
                }
            } catch (\Exception $e) {
                // Silently continue if charge retrieval fails - EUR will remain 0
            }
        }

        // Note: We calculate GROSS EUR (NET + fees) to match Stripe Dashboard
        // balance_transaction->amount = NET, balance_transaction->fee = Stripe fees

        $taxAmountCollectedEur = $taxRate > 0 ? $totalEur * $taxRate / ($taxRate + 100) : 0;

        return [
            'invoice_id' => $invoice->id,
            'created_at' => Carbon::createFromTimestamp($invoice->created)->format('Y-m-d H:i:s'),
            'cust_id' => $invoice->customer->id ?? 'unknown',
            'cust_vat_id' => $vatId,
            'cust_country' => $country,
            'tax_rate' => $taxRate,
            'total_usd' => $total / 100,
            'tax_total_usd' => $taxAmountCollectedUsd / 100,
            'total_after_tax_usd' => ($total - $taxAmountCollectedUsd) / 100,
            'total_eur' => $totalEur / 100,
            'tax_total_eur' => $taxAmountCollectedEur / 100,
            'total_after_tax_eur' => ($totalEur - $taxAmountCollectedEur) / 100,
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
