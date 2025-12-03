<?php

namespace App\Models;

use App\Models\Forms\Form;
use App\Models\Traits\CachableAttributes;
use App\Models\Traits\CachesAttributes;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable as TwoFactorAuthenticatableContract;
use Laragear\TwoFactor\TwoFactorAuthentication;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, CachableAttributes, TwoFactorAuthenticatableContract
{
    use Billable;
    use HasFactory;
    use Notifiable;
    use HasApiTokens;
    use CachesAttributes;
    use TwoFactorAuthentication;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';
    public const ROLE_READONLY = 'readonly';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_USER,
        self::ROLE_READONLY,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'hear_about_us',
        'utm_data',
        'meta',
        'blocked_at'
    ];

    protected $dispatchesEvents = [
        'created' => \App\Events\Models\UserCreated::class,
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'hear_about_us',
        'meta'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected function casts()
    {
        return [
            'email_verified_at' => 'datetime',
            'utm_data' => 'array',
            'meta' => 'array',
            'blocked_at' => 'datetime',
        ];
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'photo_url',
        'is_blocked'
    ];

    protected $cachableAttributes = [
        'has_forms',
        'is_subscribed',
        'is_pro',
        'active_license',
    ];

    public function ownsForm(Form $form)
    {
        // Use loaded relationship if available to avoid queries
        if ($this->relationLoaded('workspaces')) {
            return $this->workspaces->contains('id', $form->workspace_id);
        }

        return $this->workspaces()->where('workspaces.id', $form->workspace_id)->exists();
    }

    public function ownsWorkspace(Workspace $workspace)
    {
        // Use loaded relationship if available to avoid queries
        if ($this->relationLoaded('workspaces')) {
            return $this->workspaces->contains('id', $workspace->id);
        }

        return $this->workspaces()->where('workspaces.id', $workspace->id)->exists();
    }

    /**
     * Get the profile photo URL attribute.
     *
     * @return string
     */
    public function getPhotoUrlAttribute()
    {
        return vsprintf('https://www.gravatar.com/avatar/%s.jpg?s=200&d=%s', [
            md5(strtolower($this->email)),
            $this->name ? urlencode("https://ui-avatars.com/api/$this->name.jpg") : 'mp',
        ]);
    }

    public function getHasFormsAttribute()
    {
        return $this->remember('has_forms', 10 * 60, function (): bool {
            // Use loaded relationship if available to avoid queries
            if ($this->relationLoaded('workspaces')) {
                return $this->workspaces->some(function ($workspace) {
                    // If workspace has forms relationship loaded, use it
                    if ($workspace->relationLoaded('forms')) {
                        return $workspace->forms->isNotEmpty();
                    }
                    // Otherwise fall back to database query for this workspace
                    return $workspace->forms()->exists();
                });
            }

            return $this->workspaces()->whereHas('forms')->exists();
        });
    }

    public function getIsSubscribedAttribute()
    {
        if (!pricing_enabled()) {
            return true;
        }

        return $this->remember('is_subscribed', 5 * 60, function (): bool {
            return $this->subscribed()
                || in_array($this->email, config('opnform.extra_pro_users_emails'))
                || !is_null($this->activeLicense());
        });
    }

    public function getHasCustomerIdAttribute()
    {
        return !is_null($this->stripe_id);
    }

    public function getAdminAttribute()
    {
        return in_array($this->email, config('opnform.admin_emails'));
    }

    public function getModeratorAttribute()
    {
        return in_array($this->email, config('opnform.moderator_emails')) || $this->admin;
    }

    public function getTemplateEditorAttribute()
    {
        return $this->admin || in_array($this->email, config('opnform.template_editor_emails'));
    }

    public function getIsProAttribute()
    {
        return $this->remember('is_pro', 5 * 60, function (): bool {
            // Use loaded relationship if available to avoid queries
            if ($this->relationLoaded('workspaces')) {
                return $this->workspaces->some(function ($workspace) {
                    return $workspace->is_pro;
                });
            }

            return $this->workspaces()->get()->some(function ($workspace) {
                return $workspace->is_pro;
            });
        });
    }

    public function getIsBlockedAttribute()
    {
        return !is_null($this->blocked_at);
    }

    public function blockUser(string $reason, ?int $moderatorId): void
    {
        $this->blocked_at = now();
        $history = $this->meta['blocking_history'] ?? [];
        $history[] = [
            'reason' => $reason,
            'blocked_at' => $this->blocked_at,
            'blocked_by' => $moderatorId ?? null,
            'unblock_reason' => null,
            'unblocked_at' => null,
            'unblocked_by' => null,
        ];
        $this->meta = array_merge($this->meta ?? [], ['blocking_history' => $history]);
        $this->save();
    }

    public function unblockUser(string $reason, int $moderatorId): void
    {
        $this->blocked_at = null;
        $history = $this->meta['blocking_history'] ?? [];
        if (empty($history)) {
            $this->save();
            return;
        }

        $lastBlockKey = array_key_last($history);
        $history[$lastBlockKey]['unblock_reason'] = $reason;
        $history[$lastBlockKey]['unblocked_at'] = now();
        $history[$lastBlockKey]['unblocked_by'] = $moderatorId;

        $this->meta = array_merge($this->meta ?? [], ['blocking_history' => $history]);
        $this->save();
    }

    public function getLastBlock(): ?array
    {
        $history = $this->meta['blocking_history'] ?? [];
        if (empty($history)) {
            return null;
        }

        return end($history);
    }

    /**
     * =================================
     *  Helper Related
     * =================================
     */

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPassword($token));
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmail());
    }

    /**
     * =================================
     *  Relationship
     * =================================
     */
    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class);
    }

    public function forms()
    {
        return $this->hasMany(Form::class, 'creator_id');
    }

    public function formTemplates()
    {
        return $this->hasMany(Template::class, 'creator_id');
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    public function activeLicense(): ?License
    {
        return $this->remember('active_license', 10 * 60, function (): ?License {
            // Use loaded relationship if available - UserController loads only active licenses
            if ($this->relationLoaded('licenses')) {
                return $this->licenses->first(); // Already filtered to active in eager load
            }

            return $this->licenses()->active()->first();
        });
    }

    /**
     * =================================
     *  Oauth Related
     * =================================
     */

    /**
     * Get the oauth providers.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function oauthProviders()
    {
        return $this->hasMany(OAuthProvider::class);
    }

    /**
     * Get the OIDC user identities.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userIdentities()
    {
        return $this->hasMany(\App\Enterprise\Oidc\Models\UserIdentity::class, 'user_id');
    }

    /**
     * @return int
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'ip' => Hash::make(request()->ip()),
            'ua' => Hash::make(request()->userAgent()),
        ];
    }

    public function getIsRiskyAttribute()
    {
        return $this->created_at->isAfter(now()->subDays(3));
    }

    public function flushCache()
    {
        // Clear user's own cached attributes
        $this->flush();

        // Clear related workspace caches
        $this->workspaces()->with('forms')->get()->each(function (Workspace $workspace) {
            $workspace->flush();
            $workspace->forms->each(function (Form $form) {
                $form->flush();
            });
        });
    }

    public static function boot()
    {
        parent::boot();
        static::deleting(function (User $user) {
            // Delete all OAuth providers for this user
            $user->oauthProviders()->delete();

            // Remove user's workspace if he's the only one with this workspace
            foreach ($user->workspaces as $workspace) {
                if ($workspace->users()->count() == 1) {
                    $workspace->delete();
                } else {
                    $workspace->users()->detach($user->id);
                }
            }
        });
    }

    public function scopeWithActiveSubscription($query)
    {
        return $query->whereHas('subscriptions', function ($query) {
            $query->where(function ($q) {
                $q->where('stripe_status', 'trialing')
                    ->orWhere('stripe_status', 'active');
            });
        });
    }
}
