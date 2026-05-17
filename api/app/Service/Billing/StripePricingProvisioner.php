<?php

namespace App\Service\Billing;

use Illuminate\Support\Arr;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;

class StripePricingProvisioner
{
    public const MODE_TEST = 'test';

    public const MODE_PRODUCTION = 'production';

    public function __construct(protected StripeClient $stripeClient)
    {
    }

    public function inferModeFromSecret(?string $secret): ?string
    {
        if (!$secret) {
            return null;
        }

        if (str_starts_with($secret, 'sk_live_')) {
            return self::MODE_PRODUCTION;
        }

        if (str_starts_with($secret, 'sk_test_')) {
            return self::MODE_TEST;
        }

        return null;
    }

    public function getPlanDefinitions(string $mode, bool $includeLegacyDefault = false): array
    {
        $definitions = [
            $this->makePlanDefinition($mode, 'pro', 'pro', 'OpnForm Pro'),
            $this->makePlanDefinition($mode, 'business', 'business', 'OpnForm Business'),
            $this->makePlanDefinition($mode, 'enterprise', 'enterprise', 'OpnForm Enterprise'),
        ];

        if ($includeLegacyDefault) {
            $definitions[] = $this->makePlanDefinition($mode, 'default', 'pro', 'OpnForm Legacy Pro');
        }

        return $definitions;
    }

    public function provision(string $mode, bool $includeLegacyDefault = false, string $currency = 'usd', bool $dryRun = false): array
    {
        $results = [];

        foreach ($this->getPlanDefinitions($mode, $includeLegacyDefault) as $definition) {
            $productResult = $this->resolveProduct($definition, $dryRun);

            $monthlyPriceResult = $this->resolvePrice(
                productId: $productResult['id'],
                definition: $definition,
                interval: 'monthly',
                currency: $currency,
                dryRun: $dryRun,
            );

            $yearlyPriceResult = $this->resolvePrice(
                productId: $productResult['id'],
                definition: $definition,
                interval: 'yearly',
                currency: $currency,
                dryRun: $dryRun,
            );

            $results[] = [
                'plan' => $definition['plan'],
                'tier' => $definition['tier'],
                'product' => [
                    'id' => $productResult['id'],
                    'env_key' => $definition['env']['product_id'],
                    'action' => $productResult['action'],
                    'display_name' => $definition['product_name'],
                ],
                'prices' => [
                    'monthly' => [
                        'id' => $monthlyPriceResult['id'],
                        'env_key' => $definition['env']['monthly'],
                        'action' => $monthlyPriceResult['action'],
                        'amount' => $definition['amounts']['monthly'],
                    ],
                    'yearly' => [
                        'id' => $yearlyPriceResult['id'],
                        'env_key' => $definition['env']['yearly'],
                        'action' => $yearlyPriceResult['action'],
                        'amount' => $definition['amounts']['yearly'],
                    ],
                ],
            ];
        }

        return $results;
    }

    public function writeEnvValues(string $envPath, array $provisioned): array
    {
        $contents = file_exists($envPath) ? file_get_contents($envPath) : '';
        $contents = $contents === false ? '' : $contents;
        $written = [];

        foreach ($provisioned as $planResult) {
            $pairs = [
                $planResult['product']['env_key'] => $planResult['product']['id'],
                $planResult['prices']['monthly']['env_key'] => $planResult['prices']['monthly']['id'],
                $planResult['prices']['yearly']['env_key'] => $planResult['prices']['yearly']['id'],
            ];

            foreach ($pairs as $key => $value) {
                if (!$value) {
                    continue;
                }

                if ($this->envValueExists($contents, $key)) {
                    continue;
                }

                $contents = $this->upsertEnvValue($contents, $key, $value);
                $written[$key] = $value;
            }
        }

        file_put_contents($envPath, $contents);

        return $written;
    }

    protected function makePlanDefinition(string $mode, string $plan, string $tier, string $productName): array
    {
        $prefix = $mode === self::MODE_PRODUCTION ? 'STRIPE_PROD_' : 'STRIPE_TEST_';
        $envStub = strtoupper($plan);
        $tierConfig = config("plans.tiers.{$tier}", []);

        return [
            'plan' => $plan,
            'tier' => $tier,
            'product_name' => $productName,
            'amounts' => [
                'monthly' => (int) round(((int) Arr::get($tierConfig, 'price_monthly', 0)) * 100),
                'yearly' => (int) round(((int) Arr::get($tierConfig, 'price_yearly', 0)) * 100),
            ],
            'env' => [
                'product_id' => "{$prefix}{$envStub}_PRODUCT_ID",
                'monthly' => "{$prefix}{$envStub}_PRICING_MONTHLY",
                'yearly' => "{$prefix}{$envStub}_PRICING_YEARLY",
            ],
            'env_values' => [
                'product_id' => config("pricing.{$mode}.{$plan}.product_id"),
                'monthly' => config("pricing.{$mode}.{$plan}.pricing.monthly"),
                'yearly' => config("pricing.{$mode}.{$plan}.pricing.yearly"),
            ],
        ];
    }

    protected function resolveProduct(array $definition, bool $dryRun): array
    {
        $envProductId = $definition['env_values']['product_id'];
        if ($envProductId) {
            try {
                $product = $this->stripeClient->products->retrieve($envProductId, []);

                return [
                    'id' => $product->id,
                    'action' => 'existing_env',
                ];
            } catch (ApiErrorException) {
                // Fall through to metadata lookup or creation.
            }
        }

        $existing = $this->findProductByMetadata($definition['plan']);
        if ($existing) {
            return [
                'id' => $existing->id,
                'action' => 'existing_stripe',
            ];
        }

        if ($dryRun) {
            return [
                'id' => null,
                'action' => 'would_create',
            ];
        }

        $product = $this->stripeClient->products->create([
            'name' => $definition['product_name'],
            'metadata' => [
                'opnform_plan' => $definition['plan'],
                'opnform_tier' => $definition['tier'],
            ],
        ]);

        return [
            'id' => $product->id,
            'action' => 'created',
        ];
    }

    protected function resolvePrice(?string $productId, array $definition, string $interval, string $currency, bool $dryRun): array
    {
        $envKey = $interval === 'monthly' ? 'monthly' : 'yearly';
        $envPriceId = $definition['env_values'][$envKey];

        if ($envPriceId) {
            try {
                $price = $this->stripeClient->prices->retrieve($envPriceId, []);

                return [
                    'id' => $price->id,
                    'action' => 'existing_env',
                ];
            } catch (ApiErrorException) {
                // Fall through to lookup by product/amount.
            }
        }

        if ($productId) {
            $existing = $this->findPriceForProduct(
                productId: $productId,
                amount: $definition['amounts'][$envKey],
                interval: $interval,
                currency: $currency,
            );

            if ($existing) {
                return [
                    'id' => $existing->id,
                    'action' => 'existing_stripe',
                ];
            }
        }

        if ($dryRun) {
            return [
                'id' => null,
                'action' => 'would_create',
            ];
        }

        if (!$productId) {
            throw new \RuntimeException("Cannot create {$definition['plan']} {$interval} price without a Stripe product id.");
        }

        $price = $this->stripeClient->prices->create([
            'product' => $productId,
            'currency' => strtolower($currency),
            'unit_amount' => $definition['amounts'][$envKey],
            'recurring' => [
                'interval' => $interval === 'yearly' ? 'year' : 'month',
            ],
            'metadata' => [
                'opnform_plan' => $definition['plan'],
                'opnform_tier' => $definition['tier'],
                'opnform_interval' => $interval,
            ],
        ]);

        return [
            'id' => $price->id,
            'action' => 'created',
        ];
    }

    protected function findProductByMetadata(string $plan): ?Product
    {
        $products = $this->stripeClient->products->all(['limit' => 100]);
        foreach ($products->data as $product) {
            if (($product->metadata['opnform_plan'] ?? null) === $plan) {
                return $product;
            }
        }

        return null;
    }

    protected function findPriceForProduct(string $productId, int $amount, string $interval, string $currency): ?Price
    {
        $prices = $this->stripeClient->prices->all([
            'product' => $productId,
            'active' => true,
            'limit' => 100,
        ]);

        $expectedInterval = $interval === 'yearly' ? 'year' : 'month';

        foreach ($prices->data as $price) {
            if (
                $price->currency === strtolower($currency) &&
                $price->unit_amount === $amount &&
                ($price->recurring->interval ?? null) === $expectedInterval
            ) {
                return $price;
            }
        }

        return null;
    }

    protected function envValueExists(string $contents, string $key): bool
    {
        if (!preg_match("/^{$key}=(.*)$/m", $contents, $matches)) {
            return false;
        }

        return trim($matches[1]) !== '';
    }

    protected function upsertEnvValue(string $contents, string $key, string $value): string
    {
        if (preg_match("/^{$key}=.*$/m", $contents)) {
            return preg_replace("/^{$key}=.*$/m", "{$key}={$value}", $contents) ?? $contents;
        }

        return rtrim($contents) . PHP_EOL . "{$key}={$value}" . PHP_EOL;
    }
}
