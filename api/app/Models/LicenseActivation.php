<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseActivation extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'license_key_id',
        'instance_id',
        'status',
        'usage',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts()
    {
        return [
            'usage' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function licenseKey()
    {
        return $this->belongsTo(LicenseKey::class);
    }
}
