<?php

use App\Service\Billing\BillingStateResolver;
use App\Service\Billing\PlanTier;
use Illuminate\Support\Str;

uses(\Tests\TestCase::class);

beforeEach(function () {
    $this->resolver = app(BillingStateResolver::class);
});

it('resolves business subscriptions as paid and business tier', function () {
    $user = $this->createBusinessUser();
    $workspace = $this->createUserWorkspace($user);

    $state = $this->resolver->resolveWorkspace($workspace->fresh());

    expect($state->isPaid)->toBeTrue();
    expect($state->tier)->toBe(PlanTier::BUSINESS);
});

it('resolves workspace tier overrides without a billing owner subscription', function () {
    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);
    $workspace->update([
        'plan_overrides' => ['tier' => PlanTier::ENTERPRISE],
    ]);
    $workspace->flush();

    $state = $this->resolver->resolveWorkspace($workspace->fresh());

    expect($state->tier)->toBe(PlanTier::ENTERPRISE);
    expect($state->hasOverrides)->toBeTrue();
    expect($state->isPaid)->toBeTrue();
});

it('marks configured grandfathered prices on resolved subscriptions', function () {
    $user = $this->createUser();
    $grandfatheredPrice = (string) Str::uuid();
    config(['billing_state.grandfathered_prices' => [$grandfatheredPrice]]);

    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => (string) Str::uuid(),
        'stripe_status' => 'active',
        'stripe_price' => $grandfatheredPrice,
        'quantity' => 1,
    ]);

    $state = $this->resolver->resolveUser($user->fresh());

    expect($state->isGrandfathered)->toBeTrue();
    expect($state->tier)->toBe(PlanTier::PRO);
});

it('resolves active appsumo licenses as paid pro access', function () {
    $user = $this->createAppSumoLicensedUser(2);
    $workspace = $this->createUserWorkspace($user);

    $state = $this->resolver->resolveWorkspace($workspace->fresh());

    expect($state->isPaid)->toBeTrue();
    expect($state->hasLicense)->toBeTrue();
    expect($state->tier)->toBe(PlanTier::PRO);
});
