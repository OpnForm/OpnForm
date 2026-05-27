<?php

use App\Service\Billing\BillingStateResolver;
use App\Service\Billing\Feature;
use App\Service\Billing\PlanAccessService;
use App\Service\Billing\PlanTier;
use Illuminate\Support\Str;

uses(\Tests\TestCase::class);

function createSubscriptionForPlanOverrideTest($user, string $status = 'active')
{
    return $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => (string) Str::uuid(),
        'stripe_status' => $status,
        'stripe_price' => (string) Str::uuid(),
        'quantity' => 1,
    ]);
}

it('keeps plan overrides permanent when no subscription is linked', function () {
    $user = $this->createUser();
    $workspace = $this->createUserWorkspace($user);
    $workspace->update([
        'plan_overrides' => [
            'features' => [Feature::FORM_VERSIONING],
            'limits' => ['custom_domain_count' => 5],
            'tier' => PlanTier::BUSINESS,
        ],
    ]);

    $workspace = $workspace->fresh();
    $access = app(PlanAccessService::class);
    $state = app(BillingStateResolver::class)->resolveWorkspace($workspace);

    expect($state->tier)->toBe(PlanTier::BUSINESS);
    expect($state->hasOverrides)->toBeTrue();
    expect($access->hasFeature($workspace, Feature::FORM_VERSIONING))->toBeTrue();
    expect($access->getLimits($workspace)['custom_domain_count'])->toBe(5);
});

it('applies linked plan overrides while the subscription is active', function () {
    $user = $this->createUser();
    $subscription = createSubscriptionForPlanOverrideTest($user);
    $workspace = $this->createUserWorkspace($user);
    $workspace->update([
        'plan_overrides' => [
            'features' => [Feature::FORM_VERSIONING],
            'limits' => ['custom_domain_count' => 5],
            'tier' => PlanTier::BUSINESS,
        ],
        'plan_overrides_subscription_id' => $subscription->id,
    ]);

    $workspace = $workspace->fresh();
    $access = app(PlanAccessService::class);
    $state = app(BillingStateResolver::class)->resolveWorkspace($workspace);

    expect($state->tier)->toBe(PlanTier::BUSINESS);
    expect($state->hasOverrides)->toBeTrue();
    expect($access->hasFeature($workspace, Feature::FORM_VERSIONING))->toBeTrue();
    expect($access->getLimits($workspace)['custom_domain_count'])->toBe(5);
});

it('ignores linked plan overrides when the subscription is not active', function () {
    $user = $this->createUser();
    $subscription = createSubscriptionForPlanOverrideTest($user, 'canceled');
    $workspace = $this->createUserWorkspace($user);
    $workspace->update([
        'plan_overrides' => [
            'features' => [Feature::FORM_VERSIONING],
            'limits' => ['custom_domain_count' => 5],
            'tier' => PlanTier::BUSINESS,
        ],
        'plan_overrides_subscription_id' => $subscription->id,
    ]);

    $workspace = $workspace->fresh();
    $access = app(PlanAccessService::class);
    $state = app(BillingStateResolver::class)->resolveWorkspace($workspace);

    expect($state->tier)->toBe(PlanTier::FREE);
    expect($state->hasOverrides)->toBeFalse();
    expect($access->hasFeature($workspace, Feature::FORM_VERSIONING))->toBeFalse();
    expect($access->getLimits($workspace)['custom_domain_count'])->toBe(0);
});

it('ignores linked plan overrides when the subscription owner is not a workspace admin', function () {
    $admin = $this->createUser();
    $subscriber = $this->createUser();
    $subscription = createSubscriptionForPlanOverrideTest($subscriber);
    $workspace = $this->createUserWorkspace($admin);
    $subscriber->workspaces()->sync([
        $workspace->id => ['role' => 'user'],
    ], false);
    $workspace->update([
        'plan_overrides' => [
            'features' => [Feature::FORM_VERSIONING],
        ],
        'plan_overrides_subscription_id' => $subscription->id,
    ]);

    expect(app(PlanAccessService::class)->hasFeature($workspace->fresh(), Feature::FORM_VERSIONING))->toBeFalse();
});

it('preserves permanent overrides when linked plan overrides are inactive', function () {
    $user = $this->createUser();
    $subscription = createSubscriptionForPlanOverrideTest($user, 'canceled');
    $workspace = $this->createUserWorkspace($user);
    $workspace->update([
        'plan_overrides' => [
            'permanent' => [
                'features' => [Feature::SSO_OIDC],
                'limits' => ['custom_domain_count' => 25],
                'tier' => PlanTier::PRO,
            ],
            'features' => [Feature::FORM_VERSIONING],
            'limits' => ['custom_domain_count' => 5],
            'tier' => PlanTier::BUSINESS,
        ],
        'plan_overrides_subscription_id' => $subscription->id,
    ]);

    $workspace = $workspace->fresh();
    $access = app(PlanAccessService::class);
    $state = app(BillingStateResolver::class)->resolveWorkspace($workspace);

    expect($state->tier)->toBe(PlanTier::PRO);
    expect($access->hasFeature($workspace, Feature::SSO_OIDC))->toBeTrue();
    expect($access->hasFeature($workspace, Feature::FORM_VERSIONING))->toBeFalse();
    expect($access->getLimits($workspace)['custom_domain_count'])->toBe(25);
});
