<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseKey extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'license_key',
        'stripe_customer_id',
        'stripe_subscription_id',
        'billing_email',
        'status',
        'plan',
        'features',
        'expires_at',
    ];

    protected function casts()
    {
        return [
            'features' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function checkoutSessions()
    {
        return $this->hasMany(LicenseCheckoutSession::class);
    }

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public static function defaultEnterpriseFeatures(): array
    {
        return [
            'sso' => true,
            'multiOrg' => true,
            'whitelabel' => true,
            'custom_smtp' => true,
            'audit_logs' => true,
            'external_storage' => true,
        ];
    }
}
