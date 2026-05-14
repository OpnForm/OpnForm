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
        $extraProEmails = $this->extraProEmails();

        DB::table('workspaces')
            ->select(['id', 'plan_overrides', 'plan_overrides_subscription_id'])
            ->where(function ($query) use ($extraProEmails) {
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

                if ($extraProEmails !== []) {
                    $query->orWhereExists(function ($exists) use ($extraProEmails) {
                        $exists
                            ->selectRaw('1')
                            ->from('user_workspace')
                            ->join('users', 'users.id', '=', 'user_workspace.user_id')
                            ->whereColumn('user_workspace.workspace_id', 'workspaces.id')
                            ->where('user_workspace.role', 'admin')
                            ->whereIn('users.email', $extraProEmails);
                    });
                }
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
        $hasExtraProOwner = $this->hasExtraProOwner($workspace->id);
        $isPermanentGrandfathering = $hasActiveLifetimeLicense || $hasExtraProOwner;
        $subscriptionId = $isPermanentGrandfathering ? null : $this->getActiveLegacySubscriptionId($workspace->id);

        if (!$isPermanentGrandfathering && !$subscriptionId) {
            return;
        }

        $overrides = $this->decodeOverrides($workspace->plan_overrides ?? null);
        if (!$isPermanentGrandfathering && !is_array($overrides[self::MARKER_KEY] ?? null)) {
            $overrides = $this->moveExistingOverridesToPermanent($overrides);
        }

        $permanentFeatures = $this->normalizeStringList($overrides['permanent']['features'] ?? []);
        $scopedFeatures = $this->normalizeStringList($overrides['features'] ?? []);
        $featuresToAdd = array_values(array_diff(
            self::LEGACY_PRO_FEATURES,
            array_values(array_unique(array_merge($permanentFeatures, $scopedFeatures))),
        ));

        if ($featuresToAdd === []) {
            return;
        }

        $marker = is_array($overrides[self::MARKER_KEY] ?? null)
            ? $overrides[self::MARKER_KEY]
            : [];

        $overrides['features'] = array_values(array_unique(array_merge($scopedFeatures, $featuresToAdd)));
        $overrides[self::MARKER_KEY] = [
            'source' => $this->getGrandfatheringSource($hasActiveLifetimeLicense, $hasExtraProOwner),
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
                'plan_overrides_subscription_id' => $isPermanentGrandfathering ? null : $subscriptionId,
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
        $overrides = $this->restorePermanentOverrides($overrides);

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

    private function hasExtraProOwner(int $workspaceId): bool
    {
        $extraProEmails = $this->extraProEmails();
        if ($extraProEmails === []) {
            return false;
        }

        return DB::table('users')
            ->join('user_workspace', 'user_workspace.user_id', '=', 'users.id')
            ->where('user_workspace.workspace_id', $workspaceId)
            ->where('user_workspace.role', 'admin')
            ->whereIn('users.email', $extraProEmails)
            ->exists();
    }

    private function moveExistingOverridesToPermanent(array $overrides): array
    {
        $existingOverrides = $this->extractOverridePayload($overrides);
        if ($existingOverrides === []) {
            return $overrides;
        }

        unset($overrides['tier'], $overrides['features'], $overrides['limits']);
        $overrides['permanent'] = $this->mergeOverridePayloads(
            $this->extractOverridePayload($overrides['permanent'] ?? []),
            $existingOverrides,
        );

        return $overrides;
    }

    private function restorePermanentOverrides(array $overrides): array
    {
        $permanentOverrides = $this->extractOverridePayload($overrides['permanent'] ?? []);
        unset($overrides['permanent']);

        if ($permanentOverrides === []) {
            return $overrides;
        }

        $topLevelOverrides = $this->extractOverridePayload($overrides);
        unset($overrides['tier'], $overrides['features'], $overrides['limits']);

        return array_merge(
            $overrides,
            $this->mergeOverridePayloads($permanentOverrides, $topLevelOverrides),
        );
    }

    private function extractOverridePayload(mixed $overrides): array
    {
        if (!is_array($overrides)) {
            return [];
        }

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

    private function mergeOverridePayloads(array $existingOverrides, array $newOverrides): array
    {
        $merged = $existingOverrides;

        if (isset($newOverrides['tier'])) {
            $merged['tier'] = $newOverrides['tier'];
        }

        $features = array_values(array_unique(array_merge(
            $this->normalizeStringList($existingOverrides['features'] ?? []),
            $this->normalizeStringList($newOverrides['features'] ?? []),
        )));
        if ($features !== []) {
            $merged['features'] = $features;
        }

        $limits = array_merge(
            is_array($existingOverrides['limits'] ?? null) ? $existingOverrides['limits'] : [],
            is_array($newOverrides['limits'] ?? null) ? $newOverrides['limits'] : [],
        );
        if ($limits !== []) {
            $merged['limits'] = $limits;
        }

        return $merged;
    }

    private function getGrandfatheringSource(bool $hasActiveLifetimeLicense, bool $hasExtraProOwner): string
    {
        if ($hasActiveLifetimeLicense) {
            return 'lifetime_license';
        }

        return $hasExtraProOwner ? 'extra_pro_user' : 'legacy_default_pro';
    }

    private function extraProEmails(): array
    {
        $emails = config('opnform.extra_pro_users_emails', []);
        if (!is_array($emails)) {
            return [];
        }

        return array_values(array_filter(
            $emails,
            fn ($email) => is_string($email) && $email !== '',
        ));
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
