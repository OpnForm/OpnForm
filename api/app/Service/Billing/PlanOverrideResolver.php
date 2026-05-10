<?php

namespace App\Service\Billing;

use App\Models\Workspace;

class PlanOverrideResolver
{
    private const ACTIVE_STATUSES = ['trialing', 'active'];

    private array $effectiveOverridesCache = [];

    private array $activeLinkedSubscriptionCache = [];

    public function getEffectiveOverrides(Workspace $workspace): array
    {
        $cacheKey = $this->getEffectiveOverridesCacheKey($workspace);
        if (array_key_exists($cacheKey, $this->effectiveOverridesCache)) {
            return $this->effectiveOverridesCache[$cacheKey];
        }

        $overrides = $this->normalizeOverrides($workspace->plan_overrides ?? null);

        if ($overrides === []) {
            return $this->effectiveOverridesCache[$cacheKey] = [];
        }

        $permanentOverrides = $this->getOverridePayload($overrides['permanent'] ?? []);
        $scopedOverrides = $this->getOverridePayload($overrides);

        if (!$workspace->plan_overrides_subscription_id) {
            return $this->effectiveOverridesCache[$cacheKey] = $this->mergeOverrides(
                $permanentOverrides,
                $scopedOverrides,
            );
        }

        if (!$this->hasActiveLinkedSubscription($workspace)) {
            return $this->effectiveOverridesCache[$cacheKey] = $permanentOverrides;
        }

        return $this->effectiveOverridesCache[$cacheKey] = $this->mergeOverrides(
            $permanentOverrides,
            $scopedOverrides,
        );
    }

    public function hasActiveLinkedSubscription(Workspace $workspace): bool
    {
        $subscriptionId = $workspace->plan_overrides_subscription_id;
        if (!$subscriptionId) {
            return false;
        }

        $cacheKey = $workspace->id . ':' . $subscriptionId;
        if (array_key_exists($cacheKey, $this->activeLinkedSubscriptionCache)) {
            return $this->activeLinkedSubscriptionCache[$cacheKey];
        }

        return $this->activeLinkedSubscriptionCache[$cacheKey] = $workspace->owners()
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

    private function getOverridePayload(mixed $overrides): array
    {
        $overrides = $this->normalizeOverrides($overrides);
        $payload = [];

        if (isset($overrides['tier']) && is_string($overrides['tier'])) {
            $payload['tier'] = $overrides['tier'];
        }

        $features = $this->normalizeStringList($overrides['features'] ?? []);
        if ($features !== []) {
            $payload['features'] = $features;
        }

        if (isset($overrides['limits']) && is_array($overrides['limits'])) {
            $payload['limits'] = $overrides['limits'];
        }

        return $payload;
    }

    private function mergeOverrides(array $permanentOverrides, array $scopedOverrides): array
    {
        $merged = $permanentOverrides;

        if (isset($scopedOverrides['tier'])) {
            $merged['tier'] = $scopedOverrides['tier'];
        }

        $features = array_values(array_unique(array_merge(
            $this->normalizeStringList($permanentOverrides['features'] ?? []),
            $this->normalizeStringList($scopedOverrides['features'] ?? []),
        )));
        if ($features !== []) {
            $merged['features'] = $features;
        }

        $limits = array_merge(
            is_array($permanentOverrides['limits'] ?? null) ? $permanentOverrides['limits'] : [],
            is_array($scopedOverrides['limits'] ?? null) ? $scopedOverrides['limits'] : [],
        );
        if ($limits !== []) {
            $merged['limits'] = $limits;
        }

        return $merged;
    }

    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter($value, 'is_string')));
    }

    private function getEffectiveOverridesCacheKey(Workspace $workspace): string
    {
        return implode(':', [
            $workspace->id,
            $workspace->updated_at?->timestamp ?? 0,
            $workspace->plan_overrides_subscription_id ?? 'permanent',
            md5(json_encode($workspace->plan_overrides ?? [])),
        ]);
    }
}
