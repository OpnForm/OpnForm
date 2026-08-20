<?php

namespace App\Service\Billing;

use App\Models\License;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

class LifetimeLicenseWorkspaceEntitlements
{
    public const SOURCE_LIFETIME_LICENSE = 'lifetime_license';

    public const SOURCE_EXTRA_PRO_USER = 'extra_pro_user';

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
        $source = $this->sourceForUser($user);
        if (!$source) {
            return false;
        }

        return $this->apply($workspace, $source);
    }

    public function sourceForUser(User $user): ?string
    {
        $license = $user->activeLicense();
        if ($license && $license->license_provider === 'appsumo') {
            return self::SOURCE_LIFETIME_LICENSE;
        }

        if ($this->isExtraProUser($user)) {
            return self::SOURCE_EXTRA_PRO_USER;
        }

        return null;
    }

    public function applyForEligibleWorkspace(Workspace $workspace): bool
    {
        $extraProEmails = $this->extraProEmails();

        $admin = $workspace->owners()
            ->where(function (Builder $query) use ($extraProEmails) {
                $query->whereHas('licenses', function (Builder $licenseQuery) {
                    $licenseQuery
                        ->where('license_provider', 'appsumo')
                        ->where('status', License::STATUS_ACTIVE);
                });

                if ($extraProEmails !== []) {
                    $query->orWhereIn('email', $extraProEmails);
                }
            })
            ->first();

        if (!$admin) {
            return false;
        }

        return $this->applyForUser($workspace, $admin);
    }

    public function isComplete(Workspace $workspace): bool
    {
        $features = $this->normalizeStringList($workspace->plan_overrides['features'] ?? []);

        return array_diff($this->features(), $features) === [];
    }

    public function extraProEmails(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (mixed $email) => is_string($email) ? strtolower(trim($email)) : '',
            (array) config('opnform.extra_pro_users_emails', []),
        ))));
    }

    private function apply(Workspace $workspace, string $source): bool
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
            'source' => $source,
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

    private function isExtraProUser(User $user): bool
    {
        return in_array(strtolower($user->email), $this->extraProEmails(), true);
    }

    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter($value, 'is_string')));
    }
}
