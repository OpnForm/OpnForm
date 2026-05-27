<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseCheckoutSession extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'stripe_session_id',
        'billing_email',
        'plan',
        'period',
        'license_key_id',
        'status',
        'expires_at',
        'license_email_sent_at',
    ];

    protected function casts()
    {
        return [
            'expires_at' => 'datetime',
            'license_email_sent_at' => 'datetime',
        ];
    }

    public function licenseKey()
    {
        return $this->belongsTo(LicenseKey::class);
    }
}
