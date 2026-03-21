<?php

use App\Service\Billing\StripePricingProvisioner;
use Stripe\StripeClient;

uses(\Tests\TestCase::class);

beforeEach(function () {
    $this->provisioner = new StripePricingProvisioner(new StripeClient('sk_test_fake'));
});

it('infers provisioning mode from stripe secret prefix', function () {
    expect($this->provisioner->inferModeFromSecret('sk_test_123'))->toBe(StripePricingProvisioner::MODE_TEST);
    expect($this->provisioner->inferModeFromSecret('sk_live_123'))->toBe(StripePricingProvisioner::MODE_PRODUCTION);
    expect($this->provisioner->inferModeFromSecret('foo'))->toBeNull();
});

it('builds current plan definitions without legacy default by default', function () {
    $definitions = $this->provisioner->getPlanDefinitions(StripePricingProvisioner::MODE_TEST);

    expect(array_column($definitions, 'plan'))->toBe(['pro', 'business', 'enterprise']);
    expect($definitions[0]['env']['product_id'])->toBe('STRIPE_TEST_PRO_PRODUCT_ID');
    expect($definitions[2]['env']['yearly'])->toBe('STRIPE_TEST_ENTERPRISE_PRICING_YEARLY');
});

it('writes env values only for missing or empty keys', function () {
    $envPath = tempnam(sys_get_temp_dir(), 'stripe-pricing-env-');
    file_put_contents($envPath, "STRIPE_TEST_PRO_PRODUCT_ID=prod_existing\nSTRIPE_TEST_PRO_PRICING_MONTHLY=\n");

    $written = $this->provisioner->writeEnvValues($envPath, [[
        'product' => [
            'env_key' => 'STRIPE_TEST_PRO_PRODUCT_ID',
            'id' => 'prod_new',
        ],
        'prices' => [
            'monthly' => [
                'env_key' => 'STRIPE_TEST_PRO_PRICING_MONTHLY',
                'id' => 'price_monthly',
            ],
            'yearly' => [
                'env_key' => 'STRIPE_TEST_PRO_PRICING_YEARLY',
                'id' => 'price_yearly',
            ],
        ],
    ]]);

    $contents = file_get_contents($envPath);

    expect($written)->toBe([
        'STRIPE_TEST_PRO_PRICING_MONTHLY' => 'price_monthly',
        'STRIPE_TEST_PRO_PRICING_YEARLY' => 'price_yearly',
    ]);
    expect($contents)->toContain('STRIPE_TEST_PRO_PRODUCT_ID=prod_existing');
    expect($contents)->toContain('STRIPE_TEST_PRO_PRICING_MONTHLY=price_monthly');
    expect($contents)->toContain('STRIPE_TEST_PRO_PRICING_YEARLY=price_yearly');

    @unlink($envPath);
});

it('skips null values when writing env values', function () {
    $envPath = tempnam(sys_get_temp_dir(), 'stripe-pricing-env-');
    file_put_contents($envPath, "STRIPE_TEST_PRO_PRODUCT_ID=\n");

    $written = $this->provisioner->writeEnvValues($envPath, [[
        'product' => [
            'env_key' => 'STRIPE_TEST_PRO_PRODUCT_ID',
            'id' => null,
        ],
        'prices' => [
            'monthly' => [
                'env_key' => 'STRIPE_TEST_PRO_PRICING_MONTHLY',
                'id' => null,
            ],
            'yearly' => [
                'env_key' => 'STRIPE_TEST_PRO_PRICING_YEARLY',
                'id' => 'price_yearly',
            ],
        ],
    ]]);

    $contents = file_get_contents($envPath);

    expect($written)->toBe([
        'STRIPE_TEST_PRO_PRICING_YEARLY' => 'price_yearly',
    ]);
    expect($contents)->not()->toContain('STRIPE_TEST_PRO_PRODUCT_ID=' . PHP_EOL . 'STRIPE_TEST_PRO_PRODUCT_ID=');
    expect($contents)->toContain('STRIPE_TEST_PRO_PRODUCT_ID=');
    expect($contents)->toContain('STRIPE_TEST_PRO_PRICING_YEARLY=price_yearly');

    @unlink($envPath);
});
