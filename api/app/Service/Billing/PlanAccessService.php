<?php

namespace App\Service\Billing;

use App\Exceptions\FeatureAccessDeniedException;
use App\Models\User;
use App\Models\Workspace;
use App\Service\License\LicenseService;

class PlanAccessService
{
    public function __construct(protected BillingStateResolver $billingStateResolver)
    {
    }

    public function getTier(Workspace $workspace): string
    {
        return $this->billingStateResolver->resolveWorkspace($workspace)->tier;
    }

    public function getUserTier(User $user): string
    {
        return $this->billingStateResolver->resolveUser($user)->tier;
    }

    /**
     * Check if a feature is available on self-hosted instances.
     * License-gated features require the license; everything else is free.
     */
    public function selfHostedHasFeature(string $feature): bool
    {
        if ($this->isLicenseGatedFeature($feature)) {
            return app(LicenseService::class)->hasAppFeature($feature);
        }

        return true;
    }

    private function isLicenseGatedFeature(string $feature): bool
    {
        $mapping = config('plans.self_hosted_features', []);
        foreach ($mapping as $appFeatures) {
            if (in_array($feature, (array) $appFeatures, true)) {
                return true;
            }
        }

        return false;
    }

    public function hasFeature(Workspace $workspace, string $feature): bool
    {
        if (!$this->isKnownFeature($feature, config('plans.features', []))) {
            return false;
        }

        return $this->hasMappedFeature(
            $workspace,
            $feature,
            config('plans.features', []),
        );
    }

    public function hasFormFeature(Workspace $workspace, string $feature): bool
    {
        $featureMap = config('plans.form_features', []);
        $overrideFeatures = $workspace->plan_overrides['features'] ?? [];

        if (!array_key_exists($feature, $featureMap) && !in_array($feature, $overrideFeatures, true)) {
            return false;
        }

        return $this->hasMappedFeature(
            $workspace,
            $feature,
            $featureMap,
        );
    }

    public function requireFeature(?Workspace $workspace, string $feature): void
    {
        if (config('app.self_hosted')) {
            if (!$this->selfHostedHasFeature($feature)) {
                abort(403, 'A self-hosted license is required to use this feature.');
            }

            return;
        }

        $currentTier = $workspace ? $this->getTier($workspace) : PlanTier::FREE;
        if ($workspace && $this->hasFeature($workspace, $feature)) {
            return;
        }

        $requiredTier = $this->getRequiredTier($feature) ?? PlanTier::PRO;
        $tierName = $this->getTierDisplayName($requiredTier);

        throw new FeatureAccessDeniedException(
            feature: $feature,
            requiredTier: $requiredTier,
            currentTier: $currentTier,
            message: "A {$tierName} plan is required to use this feature.",
        );
    }

    public function userHasFeature(User $user, string $feature): bool
    {
        $requiredTier = config('plans.features.' . $feature);
        if ($requiredTier === null) {
            return false;
        }

        return $this->tierMeetsRequirement($this->getUserTier($user), $requiredTier);
    }

    public function getFeatures(Workspace $workspace): array
    {
        $workspaceFeatures = array_values(array_filter(
            array_keys(config('plans.features', [])),
            fn (string $feature) => $this->hasFeature($workspace, $feature)
        ));

        $formFeatures = array_values(array_filter(
            array_keys(config('plans.form_features', [])),
            fn (string $feature) => $this->hasFormFeature($workspace, $feature)
        ));

        return array_values(array_unique(array_merge($workspaceFeatures, $formFeatures)));
    }

    public function getRequiredTiers(): array
    {
        return array_merge(
            config('plans.features', []),
            config('plans.form_features', []),
        );
    }

    public function getLimits(Workspace $workspace): array
    {
        $limits = config('plans.limits', []);
        $resolved = [];

        foreach (array_keys($limits) as $limitKey) {
            $resolved[$limitKey] = config("plans.limits.{$limitKey}." . $this->getTier($workspace));
        }

        foreach (($workspace->plan_overrides['limits'] ?? []) as $limitKey => $value) {
            $resolved[$limitKey] = $value;
        }

        return $resolved;
    }

    public function tierMeetsRequirement(string $tier, string $requiredTier): bool
    {
        $tierOrder = PlanTier::ORDER[$tier] ?? 0;
        $requiredOrder = PlanTier::ORDER[$requiredTier] ?? 0;

        return $tierOrder >= $requiredOrder;
    }

    public function getRequiredTier(string $feature): ?string
    {
        return config('plans.features.' . $feature)
            ?? config('plans.form_features.' . $feature);
    }

    public function getFormFeatureRequiredTier(string $feature): ?string
    {
        return config('plans.form_features.' . $feature);
    }

    public function getTierDisplayName(string $tier): string
    {
        return config("plans.tiers.{$tier}.name", ucfirst($tier));
    }

    private function hasMappedFeature(Workspace $workspace, string $feature, array $featureMap): bool
    {
        if (!pricing_enabled()) {
            if (config('app.self_hosted')) {
                return $this->selfHostedHasFeature($feature);
            }
            return true;
        }

        $overrideFeatures = $workspace->plan_overrides['features'] ?? [];
        if (in_array($feature, $overrideFeatures, true)) {
            return true;
        }

        $requiredTier = $featureMap[$feature] ?? null;
        if ($requiredTier === null) {
            return false;
        }

        return $this->tierMeetsRequirement(
            $this->getTier($workspace),
            $requiredTier,
        );
    }

    private function isKnownFeature(string $feature, array $featureMap): bool
    {
        return array_key_exists($feature, $featureMap)
            || in_array($feature, $this->getOverrideFeatures(), true);
    }

    private function getOverrideFeatures(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(config('plans.features', [])),
            array_keys(config('plans.form_features', [])),
        )));
    }
}
