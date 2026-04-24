<?php

use App\Service\Billing\StripePricingProvisioner;

it('supports dry run without writing env values', function () {
    config(['cashier.secret' => 'sk_test_123']);

    $provisioner = \Mockery::mock(StripePricingProvisioner::class);
    $provisioner->shouldReceive('inferModeFromSecret')
        ->once()
        ->with('sk_test_123')
        ->andReturn(StripePricingProvisioner::MODE_TEST);
    $provisioner->shouldReceive('provision')
        ->once()
        ->with(
            StripePricingProvisioner::MODE_TEST,
            false,
            'usd',
            true,
        )
        ->andReturn([
            [
                'plan' => 'pro',
                'tier' => 'pro',
                'product' => [
                    'id' => null,
                    'env_key' => 'STRIPE_TEST_PRO_PRODUCT_ID',
                    'action' => 'would_create',
                    'display_name' => 'OpnForm Pro',
                ],
                'prices' => [
                    'monthly' => [
                        'id' => null,
                        'env_key' => 'STRIPE_TEST_PRO_PRICING_MONTHLY',
                        'action' => 'would_create',
                        'amount' => 2900,
                    ],
                    'yearly' => [
                        'id' => null,
                        'env_key' => 'STRIPE_TEST_PRO_PRICING_YEARLY',
                        'action' => 'would_create',
                        'amount' => 29900,
                    ],
                ],
            ],
        ]);
    $provisioner->shouldNotReceive('writeEnvValues');

    app()->instance(StripePricingProvisioner::class, $provisioner);

    $this->artisan('billing:provision-stripe-pricing --dry-run')
        ->expectsOutput('Dry run for Stripe mode: test')
        ->expectsOutput('PRO (OpnForm Pro)')
        ->expectsOutput('  product [would_create] STRIPE_TEST_PRO_PRODUCT_ID=(would create)')
        ->expectsOutput('  monthly [would_create] STRIPE_TEST_PRO_PRICING_MONTHLY=(would create) $29')
        ->expectsOutput('  yearly  [would_create] STRIPE_TEST_PRO_PRICING_YEARLY=(would create) $299')
        ->expectsOutput('Dry run mode: no Stripe resources were created and .env was not modified.')
        ->assertExitCode(0);
});
