<?php

namespace App\Service\Billing;

use App\Models\Workspace;

class PlanOverrideResolver
{
    private const ACTIVE_STATUSES = ['trialing', 'active'];

    public function getEffectiveOverrides(Workspace $workspace): array
    {
        $overrides = $this->normalizeOverrides($workspace->plan_overrides ?? null);

        if ($overrides === []) {
            return [];
        }

        if (!$workspace->plan_overrides_subscription_id) {
            return $overrides;
        }

        return $this->hasActiveLinkedSubscription($workspace) ? $overrides : [];
    }

    public function hasActiveLinkedSubscription(Workspace $workspace): bool
    {
        $subscriptionId = $workspace->plan_overrides_subscription_id;
        if (!$subscriptionId) {
            return false;
        }

        return $workspace->owners()
            ->whereHas('subscriptions', function ($query) use ($subscriptionId) {
                $query
                    ->where('subscriptions.id', $subscriptionId)
                    ->whereIn('subscriptions.stripe_status', self::ACTIVE_STATUSES);
            })
            ->exists();
    }

    private function normalizeOverrides(mixed $overrides): array
    {
        return is_array($overrides) ? $overrides : [];
    }
}
