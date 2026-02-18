<?php

namespace App\Service\Plan;

use App\Models\License;
use App\Models\User;
use App\Models\Workspace;

class PlanService
{
    public const TIER_FREE = 'free';
    public const TIER_PRO = 'pro';
    public const TIER_BUSINESS = 'business';
    public const TIER_ENTERPRISE = 'enterprise';

    /**
     * Tier order for comparison (higher = more features)
     */
    public const TIER_ORDER = [
        self::TIER_FREE => 0,
        self::TIER_PRO => 1,
        self::TIER_BUSINESS => 2,
        self::TIER_ENTERPRISE => 3,
    ];

    /**
     * Cache TTL in seconds (15 minutes, matching existing workspace caching)
     */
    private const CACHE_TTL = 15 * 60;

    /**
     * Get the current plan tier for a user (cached via model).
     */
    public function getUserTier(User $user): string
    {
        if (!pricing_enabled()) {
            return self::TIER_ENTERPRISE;
        }

        // Use same caching pattern as existing is_subscribed attribute
        return $user->remember('plan_tier', self::CACHE_TTL, function () use ($user): string {
            return $this->computeUserTier($user);
        });
    }

    /**
     * Compute user tier (uncached - internal use).
     * Checks all subscriptions since Cashier's subscription() only returns type='default'.
     */
    public function computeUserTier(User $user): string
    {
        // Check for active license first (AppSumo, special deals)
        if ($license = $user->activeLicense()) {
            return $this->licenseToTier($license);
        }

        // Check all Stripe subscriptions and pick highest tier
        $highestTier = self::TIER_FREE;

        foreach ($user->subscriptions as $subscription) {
            if (!$subscription->valid()) {
                continue;
            }
            $tier = $this->subscriptionToTier($subscription);
            if (self::TIER_ORDER[$tier] > self::TIER_ORDER[$highestTier]) {
                $highestTier = $tier;
            }
        }

        return $highestTier;
    }

    /**
     * Get the effective tier for a workspace (cached via model).
     * Checks workspace overrides first, then highest tier among owners.
     */
    public function getWorkspaceTier(Workspace $workspace): string
    {
        if (!pricing_enabled()) {
            return self::TIER_ENTERPRISE;
        }

        // Use workspace's caching mechanism (same as existing is_pro)
        return $workspace->remember('plan_tier', self::CACHE_TTL, function () use ($workspace): string {
            return $this->computeWorkspaceTier($workspace);
        });
    }

    /**
     * Compute workspace tier (uncached - internal use).
     */
    public function computeWorkspaceTier(Workspace $workspace): string
    {
        // 1. Check for workspace-level tier override (admin-granted)
        $overrideTier = $workspace->plan_overrides['tier'] ?? null;
        if ($overrideTier !== null && isset(self::TIER_ORDER[$overrideTier])) {
            return $overrideTier;
        }

        // 2. Find highest tier among workspace owners
        $owners = $workspace->relationLoaded('users')
            ? $workspace->users->where('pivot.role', 'admin')
            : $workspace->owners()->get();

        $highestTier = self::TIER_FREE;

        foreach ($owners as $owner) {
            // Don't use cached to avoid nested caching issues
            $ownerTier = $this->computeUserTier($owner);
            if (self::TIER_ORDER[$ownerTier] > self::TIER_ORDER[$highestTier]) {
                $highestTier = $ownerTier;
            }
        }

        return $highestTier;
    }

    /**
     * Check if a tier has access to a specific feature.
     */
    public function tierHasFeature(string $tier, string $feature): bool
    {
        $requiredTier = config("plans.features.{$feature}");

        if ($requiredTier === null) {
            return true; // Feature not defined = available to all
        }

        return $this->tierMeetsRequirement($tier, $requiredTier);
    }

    /**
     * Get a limit value for a specific tier.
     */
    public function getTierLimit(string $tier, string $limitKey): mixed
    {
        return config("plans.limits.{$limitKey}.{$tier}");
    }

    /**
     * Compare two tiers. Returns true if $tier >= $requiredTier.
     */
    public function tierMeetsRequirement(string $tier, string $requiredTier): bool
    {
        $tierOrder = self::TIER_ORDER[$tier] ?? 0;
        $requiredOrder = self::TIER_ORDER[$requiredTier] ?? 0;

        return $tierOrder >= $requiredOrder;
    }

    /**
     * Get the required tier for a feature (for UI display).
     */
    public function getRequiredTier(string $feature): ?string
    {
        return config("plans.features.{$feature}");
    }

    /**
     * Get display name for a tier.
     */
    public function getTierDisplayName(string $tier): string
    {
        return config("plans.tiers.{$tier}.name", ucfirst($tier));
    }

    /**
     * Convert subscription to tier based on subscription type (name).
     */
    protected function subscriptionToTier($subscription): string
    {
        // Use subscription type (name) to determine tier
        $tierMapping = config('plans.subscription_tier_mapping', []);

        return $tierMapping[$subscription->type] ?? self::TIER_PRO;
    }

    /**
     * Convert license to tier.
     *
     * IMPORTANT: AppSumo licenses are grandfathered and treated as Pro
     * with their specific limits (file size, domains, users) honored separately.
     */
    protected function licenseToTier(License $license): string
    {
        // AppSumo licenses are always treated as Pro tier for feature access
        // Their specific limits are handled by License model getters
        if ($license->license_provider === 'appsumo') {
            return self::TIER_PRO;
        }

        return self::TIER_PRO;
    }

    /**
     * Check if workspace has access to a specific feature.
     * Considers workspace overrides.
     */
    public function workspaceHasFeature(Workspace $workspace, string $feature): bool
    {
        // 1. Check workspace-level feature override
        $overrideFeatures = $workspace->plan_overrides['features'] ?? [];
        if (in_array($feature, $overrideFeatures)) {
            return true;
        }

        // 2. Check tier-based access
        $tier = $this->getWorkspaceTier($workspace);

        return $this->tierHasFeature($tier, $feature);
    }

    /**
     * Get a limit for a workspace, considering overrides and licenses.
     */
    public function getWorkspaceLimit(Workspace $workspace, string $limitKey): mixed
    {
        // 1. Check workspace-level override
        $overrideLimit = $workspace->plan_overrides['limits'][$limitKey] ?? null;
        if ($overrideLimit !== null) {
            return $overrideLimit;
        }

        // 2. Check for AppSumo/License limits (they have their own limit methods)
        // This is handled at the Workspace model level for max_file_size, etc.

        // 3. Use tier-based limit
        $tier = $this->getWorkspaceTier($workspace);

        return $this->getTierLimit($tier, $limitKey);
    }
}
