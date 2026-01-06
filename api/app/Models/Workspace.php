<?php

namespace App\Models;

use App\Models\Forms\Form;
use App\Models\Traits\CachableAttributes;
use App\Models\Traits\CachesAttributes;
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

    protected $fillable = [
        'name',
        'icon',
        'user_id',
        'custom_domain',
        'settings',
    ];

    protected $dispatchesEvents = [
        'created' => \App\Events\Models\WorkspaceCreated::class,
    ];

    protected $appends = [
        'is_pro',
        'is_trialing',
        'is_enterprise',
        'users_count',
    ];

    protected function casts(): array
    {
        return [
            'custom_domains' => 'array',
            'settings' => 'array'
        ];
    }

    protected $cachableAttributes = [
        'is_pro',
        'is_trialing',
        'is_enterprise',
        'is_risky',
        'submissions_count',
        'max_file_size',
        'custom_domain_count',
        'users_count',
    ];

    public function getMaxFileSizeAttribute()
    {
        if (!pricing_enabled()) {
            return self::MAX_FILE_SIZE_PRO;
        }

        return $this->remember('max_file_size', 15 * 60, function (): int {
            // Return max file size depending on subscription
            foreach ($this->owners as $owner) {
                if ($owner->is_subscribed) {
                    if ($license = $owner->activeLicense()) {
                        // In case of special License
                        return $license->max_file_size;
                    }
                }

                return self::MAX_FILE_SIZE_PRO;
            }

            return self::MAX_FILE_SIZE_FREE;
        });
    }

    public function getCustomDomainCountLimitAttribute()
    {
        if (!pricing_enabled()) {
            return null;
        }

        return $this->remember('custom_domain_count', 15 * 60, function (): ?int {
            foreach ($this->owners as $owner) {
                if ($owner->is_subscribed) {
                    if ($license = $owner->activeLicense()) {
                        // In case of special License
                        return $license->custom_domain_limit_count;
                    }

                    return self::MAX_DOMAIN_PRO;
                }
            }

            return 0;
        });
    }

    public function getIsProAttribute()
    {
        if (!pricing_enabled()) {
            return true;    // If no paid plan so TRUE for ALL
        }

        return $this->remember('is_pro', 15 * 60, function (): bool {
            // Make sure at least one owner is pro
            $owners = $this->relationLoaded('users')
                ? $this->users->where('pivot.role', 'admin')
                : $this->owners()->get();

            foreach ($owners as $owner) {
                if ($owner->is_subscribed) {
                    return true;
                }
            }

            return false;
        });
    }

    public function getIsTrialingAttribute()
    {
        if (!pricing_enabled()) {
            return false;    // If no paid plan so FALSE for ALL
        }

        return $this->remember('is_trialing', 15 * 60, function (): bool {
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

    public function getIsEnterpriseAttribute()
    {
        if (!pricing_enabled()) {
            return true;    // If no paid plan so TRUE for ALL
        }

        return $this->remember('is_enterprise', 15 * 60, function (): bool {
            // Make sure at least one owner has enterprise subscription
            $owners = $this->relationLoaded('users')
                ? $this->users->where('pivot.role', 'admin')
                : $this->owners()->get();

            foreach ($owners as $owner) {
                if ($owner->has_enterprise_subscription) {
                    return true;
                }
            }

            return false;
        });
    }

    public function getIsRiskyAttribute()
    {
        return $this->remember('is_risky', 15 * 60, function (): bool {
            foreach ($this->owners as $owner) {
                if (!$owner->is_risky) {
                    return false;
                }
            }

            return true;
        });
    }

    public function getSubmissionsCountAttribute()
    {
        return $this->remember('submissions_count', 15 * 60, function (): int {
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
        return $this->remember('users_count', 15 * 60, function (): int {
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
}
