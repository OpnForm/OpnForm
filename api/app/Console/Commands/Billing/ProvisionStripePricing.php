<?php

namespace App\Console\Commands\Billing;

use App\Service\Billing\StripePricingProvisioner;
use Illuminate\Console\Command;

class ProvisionStripePricing extends Command
{
    protected $signature = 'billing:provision-stripe-pricing
        {--mode= : Stripe mode to provision (test|production)}
        {--include-legacy-default : Also provision the legacy default plan}
        {--dry-run : Inspect and report what would be created without creating Stripe resources or writing .env}
        {--write-env : Write missing values into api/.env without overwriting existing keys}
        {--currency=usd : Currency for newly created prices}';

    protected $description = 'Create or resolve Stripe products and prices for OpnForm plans conservatively.';

    public function handle(StripePricingProvisioner $provisioner): int
    {
        $mode = $this->resolveMode($provisioner);
        if (!$mode) {
            $this->error('Could not infer Stripe mode. Pass --mode=test or --mode=production, or configure STRIPE_SECRET.');

            return self::FAILURE;
        }

        $secret = config('cashier.secret');
        if (!$secret) {
            $this->error('STRIPE_SECRET is required.');

            return self::FAILURE;
        }

        if ($mode === StripePricingProvisioner::MODE_PRODUCTION && str_starts_with($secret, 'sk_test_')) {
            $this->error('Refusing to provision production pricing with a test Stripe secret.');

            return self::FAILURE;
        }

        if ($mode === StripePricingProvisioner::MODE_TEST && str_starts_with($secret, 'sk_live_')) {
            $this->error('Refusing to provision test pricing with a live Stripe secret.');

            return self::FAILURE;
        }

        $provisioned = $provisioner->provision(
            mode: $mode,
            includeLegacyDefault: (bool) $this->option('include-legacy-default'),
            currency: (string) $this->option('currency'),
            dryRun: (bool) $this->option('dry-run'),
        );

        $this->info(($this->option('dry-run') ? 'Dry run' : 'Provisioned pricing') . " for Stripe mode: {$mode}");
        foreach ($provisioned as $planResult) {
            $this->newLine();
            $this->line(strtoupper($planResult['plan']) . " ({$planResult['product']['display_name']})");
            $this->line('  product ' . $this->formatResourceLine(
                action: $planResult['product']['action'],
                envKey: $planResult['product']['env_key'],
                value: $planResult['product']['id'],
            ));
            $this->line('  monthly ' . $this->formatResourceLine(
                action: $planResult['prices']['monthly']['action'],
                envKey: $planResult['prices']['monthly']['env_key'],
                value: $planResult['prices']['monthly']['id'],
                amount: $planResult['prices']['monthly']['amount'],
            ));
            $this->line('  yearly  ' . $this->formatResourceLine(
                action: $planResult['prices']['yearly']['action'],
                envKey: $planResult['prices']['yearly']['env_key'],
                value: $planResult['prices']['yearly']['id'],
                amount: $planResult['prices']['yearly']['amount'],
            ));
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->line('Dry run mode: no Stripe resources were created and .env was not modified.');
        } elseif ($this->option('write-env')) {
            $written = $provisioner->writeEnvValues(base_path('.env'), $provisioned);

            $this->newLine();
            $this->info('Written to .env (only keys that were empty or missing):');

            if ($written === []) {
                $this->line('  Nothing new was written.');
            } else {
                foreach ($written as $key => $value) {
                    $this->line("  {$key}={$value}");
                }
            }
        } else {
            $this->newLine();
            $this->line('Run again with --write-env to persist missing values into api/.env.');
        }

        return self::SUCCESS;
    }

    protected function resolveMode(StripePricingProvisioner $provisioner): ?string
    {
        $mode = $this->option('mode');
        if (in_array($mode, [StripePricingProvisioner::MODE_TEST, StripePricingProvisioner::MODE_PRODUCTION], true)) {
            return $mode;
        }

        return $provisioner->inferModeFromSecret(config('cashier.secret'));
    }

    protected function formatResourceLine(string $action, string $envKey, ?string $value, ?int $amount = null): string
    {
        $parts = ["[{$action}] {$envKey}=" . ($value ?? '(would create)')];

        if ($amount !== null) {
            $parts[] = '$' . number_format($amount / 100, $amount % 100 === 0 ? 0 : 2);
        }

        return implode(' ', $parts);
    }
}
