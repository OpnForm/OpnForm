<?php

namespace App\Console\Commands\Tax;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Laravel\Cashier\Cashier;
use Stripe\Invoice;

/**
 * This command generates an XML file for DES (Déclaration Européenne de Services) reporting
 * to French customs authorities. It processes Stripe invoices within a given date range
 * and creates an XML file following the official DES schema.
 *
 * The XML file includes:
 * - Company VAT number
 * - Declaration period (month/year)
 * - Declaration type (DES)
 * - Flow type (INTRODUCTION)
 * - Line items for each EU customer with:
 *   - Line number
 *   - Value
 *   - Partner VAT number
 *   - Country code
 *
 * Usage:
 * php artisan stripe:generate-des-xml --vat=FR12345678900 --start-date=2024-01-01 --end-date=2024-01-31
 *
 * Options:
 * --start-date : Start date in YYYY-MM-DD format (defaults to first day of previous month)
 * --end-date   : End date in YYYY-MM-DD format (defaults to last day of start date's month)
 * --full-month : Use the full month of the start date
 * --vat        : Company VAT number (required, must start with FR)
 */

class GenerateDesXmlExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:generate-des-xml
                            {--start-date= : Start date (YYYY-MM-DD)}
                            {--end-date= : End date (YYYY-MM-DD)}
                            {--full-month : Use the full month of the start date}
                            {--vat= : French VAT number (must start with FR)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate DES XML file for French customs';

    private const DECLARATION_TYPE = 'DES';
    private const FLUX_TYPE = 'INTRODUCTION';

    public function handle()
    {
        // Validate required options
        $vatNumber = $this->option('vat');
        if (!$vatNumber) {
            $this->error('VAT number is required');
            return Command::FAILURE;
        }

        // Validate VAT number format
        if (!preg_match('/^FR[0-9A-Z]{11}$/', $vatNumber)) {
            $this->error('Invalid French VAT number format. Must start with FR followed by 11 characters.');
            return Command::FAILURE;
        }

        // Get dates
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

        // Get invoices
        $invoices = $this->getInvoices($startDate, $endDate);

        // Generate XML
        $xml = $this->generateXml($invoices);

        // Save XML file with .xml extension
        $period = Carbon::parse($startDate)->format('Ym');
        $filename = "DES_{$period}.xml";
        file_put_contents(storage_path("app/{$filename}"), $xml->asXML());

        $this->info("XML file generated: " . storage_path("app/{$filename}"));

        return Command::SUCCESS;
    }

    private function getInvoices($startDate, $endDate)
    {
        $processedInvoices = [];

        $queryOptions = [
            'limit' => 100,
            'expand' => [
                'data.customer',
                'data.customer.address',
                'data.customer.tax_ids',
                'data.payment_intent',
                'data.payment_intent.payment_method',
                'data.charge.balance_transaction'
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

        do {
            if (empty($invoices->data)) {
                $this->line('No invoices found in this batch.');
                break;
            }

            foreach ($invoices as $invoice) {
                // Skip cancelled or uncollectible invoices
                if ($invoice->status === 'void' || $invoice->status === 'uncollectible' || $invoice->amount_remaining === $invoice->total) {
                    continue;
                }

                // Check for payment status
                // Since we query with status='paid', check invoice status first
                // payment_intent->status may not always be available/expanded
                $invoiceStatus = $invoice->status ?? null;
                $paymentIntentStatus = $invoice->payment_intent->status ?? null;

                // Accept if invoice is paid OR payment_intent succeeded
                if ($invoiceStatus !== 'paid' && $paymentIntentStatus !== 'succeeded') {
                    continue;
                }

                // Only process EU B2B invoices with VAT number
                if (!$this->isEligibleForDes($invoice)) {
                    continue;
                }

                try {
                    $processedInvoices[] = $this->formatInvoiceForDes($invoice);
                    $bar->advance();
                } catch (\Exception $e) {
                    $this->warn("Skipping invoice {$invoice->id}: {$e->getMessage()}");
                }
            }

            if (!empty($invoices->data)) {
                $queryOptions['starting_after'] = end($invoices->data)->id;
            } else {
                break;
            }

            // No need for sleep - Stripe API can handle the request rate
            $invoices = Cashier::stripe()->invoices->all($queryOptions);
            $batchSize = count($invoices->data);
            $totalResults += $batchSize;
        } while ($invoices->has_more);

        $bar->finish();
        $this->line('');

        return $processedInvoices;
    }

    private function isEligibleForDes(Invoice $invoice): bool
    {
        $country = $invoice->customer->address->country ?? null;

        // Find VAT ID among tax IDs
        $vatId = null;
        if (!empty($invoice->customer->tax_ids->data)) {
            foreach ($invoice->customer->tax_ids->data as $taxId) {
                if ($taxId->type === 'eu_vat') {
                    $vatId = $taxId->value;
                    break;
                }
            }
        }

        // Only include EU B2B transactions (with VAT number)
        if (!$country || $country === 'FR' || !isset(GenerateTaxExport::EU_TAX_RATES[$country]) || !$vatId) {
            return false;
        }

        // Validate VAT number format: should start with 2 letters and contain at least one number
        $vatId = $this->cleanVatNumber($vatId);
        if (!preg_match('/^[A-Z]{2}[A-Z0-9]+$/', $vatId)) {
            $this->warn("Invalid VAT number format for invoice {$invoice->id}: {$vatId} (country: {$country})");
            return false;
        }

        // Also verify that the country code matches
        if (substr($vatId, 0, 2) !== $country) {
            $this->warn("VAT number country code doesn't match address for invoice {$invoice->id}: {$vatId} (country: {$country})");
            return false;
        }

        return true;
    }

    private function formatInvoiceForDes(Invoice $invoice): array
    {
        $country = $invoice->customer->address->country;

        // Find VAT ID
        $vatId = null;
        foreach ($invoice->customer->tax_ids->data as $taxId) {
            if ($taxId->type === 'eu_vat') {
                $vatId = $taxId->value;
                break;
            }
        }

        if (!$vatId) {
            throw new \InvalidArgumentException("No EU VAT number found for invoice {$invoice->id}");
        }

        // Get amount in EUR
        $amount = $this->getInvoiceAmountInEur($invoice);
        if ($amount === null) {
            throw new \RuntimeException("Could not determine EUR amount for invoice {$invoice->id}");
        }

        return [
            'country_code' => $country,
            'vat_number' => $this->cleanVatNumber($vatId),
            'amount_eur' => $amount,
            'created_at' => $invoice->created,
        ];
    }

    private function getInvoiceAmountInEur(Invoice $invoice): ?float
    {
        // If the invoice is already in EUR, just convert from cents
        if ($invoice->currency === 'eur') {
            return $invoice->amount_paid / 100;
        }

        // Try to get the converted amount from the balance transaction
        // Fast path: invoice has embedded charge
        if (isset($invoice->charge) && isset($invoice->charge->balance_transaction)) {
            $bt = $invoice->charge->balance_transaction;
            // balance_transaction->amount is NET (after fees), add fees back to get GROSS
            $netEur = $bt->amount ?? 0;
            $feeEur = $bt->fee ?? 0;
            $totalEur = $netEur + $feeEur; // GROSS = NET + fees
            return $totalEur / 100;
        }

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
                return $totalEur / 100;
            }
        } catch (\Exception $e) {
            // Silently continue if charge retrieval fails
        }

        // Try to get the amount from payment intent (if expanded)
        if (isset($invoice->payment_intent) && isset($invoice->payment_intent->charges) && isset($invoice->payment_intent->charges->data)) {
            foreach ($invoice->payment_intent->charges->data as $charge) {
                if (isset($charge->balance_transaction)) {
                    $bt = $charge->balance_transaction;
                    $netEur = $bt->amount ?? 0;
                    $feeEur = $bt->fee ?? 0;
                    $totalEur = $netEur + $feeEur;
                    return $totalEur / 100;
                }
            }
        }

        // If we can't find the EUR amount, log it and return null
        $this->warn("Could not find EUR amount for invoice {$invoice->id} in {$invoice->currency}");
        return null;
    }

    /**
     * Get exchange rate for a currency to EUR
     * You might want to use a more sophisticated exchange rate service in production
     */
    private function getExchangeRate(string $currency): float
    {
        // For now, we'll throw an exception if we can't get the exchange rate from Stripe
        throw new \RuntimeException("Unable to convert {$currency} to EUR. No exchange rate available.");
    }

    private function cleanVatNumber(string $vatId): string
    {
        // Clean any special characters and convert to uppercase
        return strtoupper(str_replace(['.', '-', ' '], '', $vatId));
    }

    private function generateXml(array $invoices): \SimpleXMLElement
    {
        $this->info('Generating XML file...');
        $bar = $this->output->createProgressBar(3);

        // Create XML without namespace (as per example)
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><fichier_des></fichier_des>');

        // Group invoices by month first, then by VAT number
        $invoicesByMonth = [];
        foreach ($invoices as $invoice) {
            $month = Carbon::createFromTimestamp($invoice['created_at'])->format('Y-m');
            if (!isset($invoicesByMonth[$month])) {
                $invoicesByMonth[$month] = [];
            }

            $key = $invoice['vat_number']; // Use full VAT number as key since it includes country code
            if (!isset($invoicesByMonth[$month][$key])) {
                $invoicesByMonth[$month][$key] = [
                    'country_code' => $invoice['country_code'],
                    'vat_number' => $invoice['vat_number'],
                    'amount_eur' => 0,
                ];
            }
            $invoicesByMonth[$month][$key]['amount_eur'] += $invoice['amount_eur'];
        }

        $bar->advance();

        // Sort months chronologically
        ksort($invoicesByMonth);

        // Create a declaration for each month
        foreach ($invoicesByMonth as $month => $groupedInvoices) {
            $monthDate = Carbon::createFromFormat('Y-m', $month);

            // Add declaration header for this month
            $declaration = $xml->addChild('declaration_des');
            $declaration->addChild('num_des', '00001');
            $declaration->addChild('num_tvaFr', $this->option('vat'));
            $declaration->addChild('mois_des', $monthDate->format('m'));
            $declaration->addChild('an_des', $monthDate->format('Y'));

            // Add line items for this month
            $lineNumber = 1;
            foreach ($groupedInvoices as $line) {
                $item = $declaration->addChild('ligne_des');
                $item->addChild('numlin_des', str_pad($lineNumber++, 6, '0', STR_PAD_LEFT)); // 6 digits as per example
                $item->addChild('valeur', round($line['amount_eur'])); // Round to nearest euro as per DES requirements
                $item->addChild('partner_des', $line['vat_number']); // Use full VAT number from Stripe
            }
        }

        $bar->advance();
        $bar->finish();
        $this->line('');

        // Format XML with proper indentation
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return new \SimpleXMLElement($dom->saveXML());
    }
}
