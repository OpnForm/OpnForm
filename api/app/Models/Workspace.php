<?php

namespace App\Models;

use App\Models\Forms\Form;
use App\Models\Traits\CachableAttributes;
use App\Models\Traits\CachesAttributes;
use App\Service\BillingHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Workspace extends Model implements CachableAttributes
{
    use CachesAttributes;
    use HasFactory;

    public const MAX_FILE_SIZE_FREE = 5000000; // 5 MB

    public const MAX_FILE_SIZE_PRO = 50000000; // 50 MB

    public const MAX_DOMAIN_PRO = 1;

    private const CACHE_TTL = 15 * 60;

    protected $fillable = [
        'name',
        'icon',
        'user_id',
        'custom_domain',
        'settings',
        'plan_overrides',
    ];

    protected $dispatchesEvents = [
        'created' => \App\Events\Models\WorkspaceCreated::class,
    ];

    protected $appends = [
        'plan_tier',
        'is_pro',
        'is_business',
        'is_trialing',
        'is_enterprise',
        'users_count',
        'is_yearly_plan',
    ];

    protected function casts(): array
    {
        return [
            'custom_domains' => 'array',
            'settings' => 'array',
            'plan_overrides' => 'array',
        ];
    }

    protected $cachableAttributes = [
        'plan_tier',
        'is_pro',
        'is_business',
        'is_trialing',
        'is_enterprise',
        'is_risky',
        'is_yearly_plan',
        'submissions_count',
        'max_file_size',
        'custom_domain_count',
        'users_count',
    ];

    /**
     * Flush workspace cache and also flush owners' cache (is_pro depends on workspace tier).
     */
    public function flush(): bool
    {
        $result = parent::flush();

        foreach ($this->owners as $owner) {
            $owner->flush();
        }

        return $result;
    }

    public function getMaxFileSizeAttribute()
    {
        if (!pricing_enabled()) {
            return self::MAX_FILE_SIZE_PRO;
        }

        return $this->remember('max_file_size', self::CACHE_TTL, function (): int {
            // 1. Check workspace-level override
            $overrideLimit = $this->plan_overrides['limits']['file_upload_size'] ?? null;
            if ($overrideLimit !== null) {
                return (int) $overrideLimit;
            }

            // 2. Check for AppSumo/License limits (take precedence over tier)
            foreach ($this->owners as $owner) {
                if ($license = $owner->activeLicense()) {
                    return $license->max_file_size;
                }
            }

            // 3. Use tier-based limit from config
            $tier = $this->plan_tier;

            return config("plans.limits.file_upload_size.{$tier}") ?? self::MAX_FILE_SIZE_FREE;
        });
    }

    public function getCustomDomainCountLimitAttribute()
    {
        if (!pricing_enabled()) {
            return null;
        }

        return $this->remember('custom_domain_count', self::CACHE_TTL, function (): ?int {
            // 1. Check workspace-level override
            $overrideLimit = $this->plan_overrides['limits']['custom_domain_count'] ?? null;
            if ($overrideLimit !== null) {
                return (int) $overrideLimit;
            }

            // 2. Check for AppSumo/License limits (take precedence over tier)
            foreach ($this->owners as $owner) {
                if ($license = $owner->activeLicense()) {
                    return $license->custom_domain_limit_count;
                }
            }

            // 3. Use tier-based limit from config
            $tier = $this->plan_tier;

            return config("plans.limits.custom_domain_count.{$tier}") ?? 0;
        });
    }

    /**
     * Get the workspace's effective plan tier.
     * Checks overrides first, then highest owner tier.
     *
     * @return string One of: 'free', 'pro', 'business', 'enterprise'
     */
    public function getPlanTierAttribute(): string
    {
        return app(\App\Service\Plan\PlanService::class)->getWorkspaceTier($this);
    }

    /**
     * Check if workspace has Pro-level access or higher.
     * Kept for backward compatibility - use plan_tier for new code.
     */
    public function getIsProAttribute()
    {
        if (!pricing_enabled()) {
            return true;    // If no paid plan so TRUE for ALL
        }

        return $this->remember('is_pro', self::CACHE_TTL, function (): bool {
            $tier = app(\App\Service\Plan\PlanService::class)->computeWorkspaceTier($this);

            return in_array($tier, ['pro', 'business', 'enterprise']);
        });
    }

    public function getIsBusinessAttribute()
    {
        if (!pricing_enabled()) {
            return true;    // If no paid plan so TRUE for ALL
        }

        return $this->remember('is_business', self::CACHE_TTL, function (): bool {
            $tier = app(\App\Service\Plan\PlanService::class)->computeWorkspaceTier($this);

            return in_array($tier, ['business', 'enterprise']);
        });
    }

    public function getIsTrialingAttribute()
    {
        if (!pricing_enabled()) {
            return false;    // If no paid plan so FALSE for ALL
        }

        return $this->remember('is_trialing', self::CACHE_TTL, function (): bool {
            // Make sure at least one owner is trialing
            $owners = $this->relationLoaded('users')
                ? $this->users->where('pivot.role', 'admin')
                : $this->owners()->get();

            foreach ($owners as $owner) {
                if ($owner->onTrial()) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Check if workspace has Enterprise-level access.
     * Kept for backward compatibility - use plan_tier for new code.
     */
    public function getIsEnterpriseAttribute()
    {
        if (!pricing_enabled()) {
            return true;    // If no paid plan so TRUE for ALL
        }

        return $this->remember('is_enterprise', self::CACHE_TTL, function (): bool {
            $tier = app(\App\Service\Plan\PlanService::class)->computeWorkspaceTier($this);

            return $tier === 'enterprise';
        });
    }

    public function getIsRiskyAttribute()
    {
        return $this->remember('is_risky', self::CACHE_TTL, function (): bool {
            foreach ($this->owners as $owner) {
                if (!$owner->is_risky) {
                    return false;
                }
            }

            return true;
        });
    }

    public function getIsYearlyPlanAttribute()
    {
        if (!pricing_enabled()) {
            return false;
        }

        return $this->remember('is_yearly_plan', self::CACHE_TTL, function (): bool {
            $owners = $this->relationLoaded('users')
                ? $this->users->where('pivot.role', 'admin')
                : $this->owners()->get();

            foreach ($owners as $owner) {
                if ($owner->is_subscribed) {
                    $subscription = $owner->subscription();
                    if ($subscription && BillingHelper::getSubscriptionInterval($subscription) === 'yearly') {
                        return true;
                    }
                }
            }

            return false;
        });
    }

    public function getSubmissionsCountAttribute()
    {
        return $this->remember('submissions_count', self::CACHE_TTL, function (): int {
            $total = 0;
            // Use loaded relationship if available to avoid queries
            $forms = $this->relationLoaded('forms')
                ? $this->forms
                : $this->forms()->get();

            foreach ($forms as $form) {
                $total += $form->submissions_count;
            }

            return $total;
        });
    }

    public function getUsersCountAttribute()
    {
        return $this->remember('users_count', self::CACHE_TTL, function (): int {
            // Use loaded relationship if available to avoid queries
            if ($this->relationLoaded('users')) {
                return $this->users->count();
            }
            return $this->users()->count();
        });
    }

    /**
     * Relationships
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function invites()
    {
        return $this->hasMany(UserInvite::class);
    }

    public function owners()
    {
        return $this->users()->wherePivot('role', 'admin');
    }

    public function billingOwners(): Collection
    {
        return $this->owners->filter(fn ($owner) => $owner->is_subscribed);
    }

    public function forms()
    {
        return $this->hasMany(Form::class);
    }

    /**
     * Get the OIDC identity connections for this workspace.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function identityConnections()
    {
        return $this->hasMany(\App\Enterprise\Oidc\Models\IdentityConnection::class);
    }

    /**
     * Check if the given OAuthProvider ID belongs to any user in this workspace.
     *
     * @param int $providerId
     * @return bool
     */
    public function hasProvider(int $providerId): bool
    {
        // Check if there's an intersection between workspace users and the provider owner
        return $this->users()->whereHas('oauthProviders', function ($query) use ($providerId) {
            $query->where('id', $providerId);
        })->exists();
    }

    public function isAdminUser(?User $user)
    {
        if (!$user) {
            return false;
        }

        // Use loaded relationship if available to avoid queries
        if ($this->relationLoaded('users')) {
            $pivot = $this->users->where('id', $user->id)->first()?->pivot;
            if ($pivot && isset($pivot->role)) {
                return $pivot->role === User::ROLE_ADMIN;
            }
        }

        return $this->users()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('role', User::ROLE_ADMIN)
            ->exists();
    }

    public function isReadonlyUser(?User $user)
    {
        if (!$user) {
            return false;
        }

        // Use loaded relationship if available to avoid queries
        if ($this->relationLoaded('users')) {
            $pivot = $this->users->where('id', $user->id)->first()?->pivot;
            if ($pivot && isset($pivot->role)) {
                return $pivot->role === User::ROLE_READONLY;
            }
        }

        return $this->users()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('role', User::ROLE_READONLY)
            ->exists();
    }

    /**
     * Check if workspace has access to a specific feature.
     * Considers workspace overrides and tier-based access.
     */
    public function hasFeature(string $feature): bool
    {
        return $this->remember('has_feature_' . $feature, self::CACHE_TTL, function () use ($feature): bool {
            return app(\App\Service\Plan\PlanService::class)->workspaceHasFeature($this, $feature);
        });
    }
}
