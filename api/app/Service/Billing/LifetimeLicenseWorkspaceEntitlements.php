<?php

namespace App\Service\Billing;

use App\Models\License;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

class LifetimeLicenseWorkspaceEntitlements
{
    public const SOURCE_LIFETIME_LICENSE = 'lifetime_license';

    public const MARKER_KEY = 'legacy_pro_grandfathering';

    private const LEGACY_PRO_FEATURES = [
        'branding.advanced',
        'multi_user.roles',
        'partial_submissions',
        'enable_partial_submissions',
        'database_fields_update',
        'enable_ip_tracking',
        'custom_code',
        'custom_css',
        'seo_meta',
        'sso.oidc',
    ];

    public function features(): array
    {
        return self::LEGACY_PRO_FEATURES;
    }

    public function applyForUser(Workspace $workspace, User $user): bool
    {
        $license = $user->activeLicense();
        if (!$license || $license->license_provider !== 'appsumo') {
            return false;
        }

        return $this->apply($workspace);
    }

    public function applyForEligibleWorkspace(Workspace $workspace): bool
    {
        $admin = $workspace->owners()
            ->whereHas('licenses', function (Builder $query) {
                $query
                    ->where('license_provider', 'appsumo')
                    ->where('status', License::STATUS_ACTIVE);
            })
            ->first();

        if (!$admin) {
            return false;
        }

        return $this->apply($workspace);
    }

    public function isComplete(Workspace $workspace): bool
    {
        $features = $this->normalizeStringList($workspace->plan_overrides['features'] ?? []);

        return array_diff($this->features(), $features) === [];
    }

    private function apply(Workspace $workspace): bool
    {
        $overrides = is_array($workspace->plan_overrides) ? $workspace->plan_overrides : [];
        $features = $this->normalizeStringList($overrides['features'] ?? []);
        $featuresToAdd = array_values(array_diff($this->features(), $features));

        if ($featuresToAdd === [] && is_array($overrides[self::MARKER_KEY] ?? null)) {
            return false;
        }

        $marker = is_array($overrides[self::MARKER_KEY] ?? null)
            ? $overrides[self::MARKER_KEY]
            : [];

        $overrides['features'] = array_values(array_unique(array_merge($features, $featuresToAdd)));
        $overrides[self::MARKER_KEY] = [
            'source' => self::SOURCE_LIFETIME_LICENSE,
            'subscription_id' => null,
            'features' => array_values(array_unique(array_merge(
                $this->normalizeStringList($marker['features'] ?? []),
                $this->features(),
            ))),
        ];

        $workspace->forceFill([
            'plan_overrides' => $overrides,
            'plan_overrides_subscription_id' => null,
        ])->save();

        $workspace->flushWithOwners();

        return true;
    }

    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter($value, 'is_string')));
    }
}
