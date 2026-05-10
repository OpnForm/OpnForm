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
            ->select(['id', 'plan_overrides'])
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
            ->select(['id', 'plan_overrides'])
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
            'source' => 'legacy_default_pro',
            'features' => array_values(array_unique(array_merge(
                $this->normalizeStringList($marker['features'] ?? []),
                $featuresToAdd,
            ))),
        ];

        DB::table('workspaces')
            ->where('id', $workspace->id)
            ->update([
                'plan_overrides' => json_encode($overrides),
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

        unset($overrides[self::MARKER_KEY]);

        DB::table('workspaces')
            ->where('id', $workspace->id)
            ->update([
                'plan_overrides' => $overrides === [] ? null : json_encode($overrides),
            ]);
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
