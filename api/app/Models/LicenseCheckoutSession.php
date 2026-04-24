<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseCheckoutSession extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'stripe_session_id',
        'billing_email',
        'plan',
        'period',
        'license_key_id',
        'status',
        'expires_at',
    ];

    protected function casts()
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function licenseKey()
    {
        return $this->belongsTo(LicenseKey::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
