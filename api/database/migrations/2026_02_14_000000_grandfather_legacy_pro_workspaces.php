<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    private const ACTIVE_STATUSES = ['trialing', 'active'];

    private const MARKER_KEY = 'legacy_pro_grandfathering';

    /**
     * Features that were effectively available to legacy Pro workspaces before
     * the multi-tier pricing split, but now require Business or Enterprise.
     */
    private const LEGACY_PRO_FEATURES = [
        'branding.advanced',
        'multi_user.roles',
        'partial_submissions',
        'enable_partial_submissions',
        'database_fields_update',
        'enable_ip_tracking',
        'custom_css',
        'seo_meta',
        'sso.oidc',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('workspaces')
            ->select(['id', 'plan_overrides', 'plan_overrides_subscription_id'])
            ->where(function ($query) {
                $query
                    ->whereExists(function ($exists) {
                        $exists
                            ->selectRaw('1')
                            ->from('user_workspace')
                            ->join('subscriptions', 'subscriptions.user_id', '=', 'user_workspace.user_id')
                            ->whereColumn('user_workspace.workspace_id', 'workspaces.id')
                            ->where('user_workspace.role', 'admin')
                            ->where('subscriptions.type', 'default')
                            ->whereIn('subscriptions.stripe_status', self::ACTIVE_STATUSES);
                    })
                    ->orWhereExists(function ($exists) {
                        $exists
                            ->selectRaw('1')
                            ->from('user_workspace')
                            ->join('licenses', 'licenses.user_id', '=', 'user_workspace.user_id')
                            ->whereColumn('user_workspace.workspace_id', 'workspaces.id')
                            ->where('user_workspace.role', 'admin')
                            ->where('licenses.status', 'active');
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function ($workspaces) {
                foreach ($workspaces as $workspace) {
                    $this->grandfatherWorkspace($workspace);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('workspaces')
            ->select(['id', 'plan_overrides', 'plan_overrides_subscription_id'])
            ->whereNotNull('plan_overrides')
            ->orderBy('id')
            ->chunkById(100, function ($workspaces) {
                foreach ($workspaces as $workspace) {
                    $this->removeGrandfatheredFeatures($workspace);
                }
            });
    }

    private function grandfatherWorkspace(object $workspace): void
    {
        $hasActiveLifetimeLicense = $this->hasActiveLifetimeLicense($workspace->id);
        $subscriptionId = $hasActiveLifetimeLicense ? null : $this->getActiveLegacySubscriptionId($workspace->id);

        if (!$hasActiveLifetimeLicense && !$subscriptionId) {
            return;
        }

        $overrides = $this->decodeOverrides($workspace->plan_overrides ?? null);
        $existingFeatures = $this->normalizeStringList($overrides['features'] ?? []);
        $featuresToAdd = array_values(array_diff(self::LEGACY_PRO_FEATURES, $existingFeatures));

        if ($featuresToAdd === []) {
            return;
        }

        $marker = is_array($overrides[self::MARKER_KEY] ?? null)
            ? $overrides[self::MARKER_KEY]
            : [];

        $overrides['features'] = array_values(array_unique(array_merge($existingFeatures, $featuresToAdd)));
        $overrides[self::MARKER_KEY] = [
            'source' => $hasActiveLifetimeLicense ? 'lifetime_license' : 'legacy_default_pro',
            'subscription_id' => $subscriptionId,
            'features' => array_values(array_unique(array_merge(
                $this->normalizeStringList($marker['features'] ?? []),
                $featuresToAdd,
            ))),
        ];

        DB::table('workspaces')
            ->where('id', $workspace->id)
            ->update([
                'plan_overrides' => json_encode($overrides),
                'plan_overrides_subscription_id' => $hasActiveLifetimeLicense ? null : $subscriptionId,
            ]);
    }

    private function removeGrandfatheredFeatures(object $workspace): void
    {
        $overrides = $this->decodeOverrides($workspace->plan_overrides ?? null);
        $marker = $overrides[self::MARKER_KEY] ?? null;

        if (!is_array($marker)) {
            return;
        }

        $featuresToRemove = $this->normalizeStringList($marker['features'] ?? []);
        $existingFeatures = $this->normalizeStringList($overrides['features'] ?? []);
        $remainingFeatures = array_values(array_diff($existingFeatures, $featuresToRemove));

        if ($remainingFeatures === []) {
            unset($overrides['features']);
        } else {
            $overrides['features'] = $remainingFeatures;
        }

        $subscriptionId = $marker['subscription_id'] ?? null;
        unset($overrides[self::MARKER_KEY]);

        $updates = [
            'plan_overrides' => $overrides === [] ? null : json_encode($overrides),
        ];

        if ($workspace->plan_overrides_subscription_id === $subscriptionId) {
            $updates['plan_overrides_subscription_id'] = null;
        }

        DB::table('workspaces')
            ->where('id', $workspace->id)
            ->update($updates);
    }

    private function getActiveLegacySubscriptionId(int $workspaceId): ?int
    {
        $subscription = DB::table('subscriptions')
            ->select('subscriptions.id')
            ->join('user_workspace', 'user_workspace.user_id', '=', 'subscriptions.user_id')
            ->where('user_workspace.workspace_id', $workspaceId)
            ->where('user_workspace.role', 'admin')
            ->where('subscriptions.type', 'default')
            ->whereIn('subscriptions.stripe_status', self::ACTIVE_STATUSES)
            ->orderByDesc('subscriptions.created_at')
            ->orderByDesc('subscriptions.id')
            ->first();

        return $subscription ? (int) $subscription->id : null;
    }

    private function hasActiveLifetimeLicense(int $workspaceId): bool
    {
        return DB::table('licenses')
            ->join('user_workspace', 'user_workspace.user_id', '=', 'licenses.user_id')
            ->where('user_workspace.workspace_id', $workspaceId)
            ->where('user_workspace.role', 'admin')
            ->where('licenses.status', 'active')
            ->exists();
    }

    private function decodeOverrides(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!$value) {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter($value, 'is_string')));
    }
};
