<?php

use App\Models\Workspace;
use App\Service\Billing\Feature;
use App\Service\Billing\LifetimeLicenseWorkspaceEntitlements;
use App\Service\Billing\PlanAccessService;
use App\Service\Billing\PlanTier;

it('applies legacy AppSumo entitlements when a licensed user creates a workspace', function () {
    $user = $this->createAppSumoLicensedUser(tier: 3);
    $this->actingAsUser($user);

    $response = $this->postJson(route('open.workspaces.create'), [
        'name' => 'Market',
        'icon' => 'M',
    ])->assertSuccessful();

    $workspace = Workspace::findOrFail($response->json('workspace_id'));
    $features = app(LifetimeLicenseWorkspaceEntitlements::class)->features();

    expect($workspace->plan_overrides['features'])->toEqualCanonicalizing($features)
        ->and($workspace->plan_overrides['legacy_pro_grandfathering']['source'])->toBe('lifetime_license')
        ->and($workspace->plan_overrides_subscription_id)->toBeNull()
        ->and(app(PlanAccessService::class)->getTier($workspace))->toBe(PlanTier::PRO)
        ->and(app(PlanAccessService::class)->hasFeature($workspace, Feature::CUSTOM_CODE))->toBeTrue()
        ->and($response->json('workspace.features'))->toContain(Feature::CUSTOM_CODE, Feature::SSO_OIDC);
});

it('does not apply legacy entitlements for regular users creating a workspace', function () {
    $user = $this->actingAsProUser();

    $response = $this->postJson(route('open.workspaces.create'), [
        'name' => 'Regular Workspace',
        'icon' => 'R',
    ])->assertSuccessful();

    $workspace = Workspace::findOrFail($response->json('workspace_id'));

    expect($workspace->plan_overrides)->toBeNull();
});

it('merges AppSumo legacy entitlements with existing workspace overrides', function () {
    $user = $this->createAppSumoLicensedUser(tier: 3);
    $workspace = $this->createUserWorkspace($user);
    $workspace->update([
        'plan_overrides' => [
            'features' => [Feature::FORM_VERSIONING],
            'limits' => ['custom_domain_count' => 50],
        ],
    ]);

    app(LifetimeLicenseWorkspaceEntitlements::class)->applyForUser($workspace->fresh(), $user);

    $workspace = $workspace->fresh();

    expect($workspace->plan_overrides['features'])
        ->toContain(Feature::FORM_VERSIONING, Feature::CUSTOM_CODE, Feature::SSO_OIDC)
        ->and($workspace->plan_overrides['limits']['custom_domain_count'])->toBe(50);
});

it('backfills missing AppSumo lifetime workspace entitlements only when applied', function () {
    $user = $this->createAppSumoLicensedUser(tier: 3);
    $workspace = $this->createUserWorkspace($user);

    $this->artisan('billing:backfill-lifetime-license-workspace-entitlements', [
        '--workspace-id' => $workspace->id,
    ])
        ->expectsOutput("would_update workspace={$workspace->id}")
        ->assertSuccessful();

    expect($workspace->fresh()->plan_overrides)->toBeNull();

    $this->artisan('billing:backfill-lifetime-license-workspace-entitlements', [
        '--workspace-id' => $workspace->id,
        '--apply' => true,
    ])
        ->expectsOutput("updated workspace={$workspace->id}")
        ->assertSuccessful();

    expect($workspace->fresh()->plan_overrides['features'])
        ->toContain(Feature::CUSTOM_CODE, Feature::SSO_OIDC);
});
