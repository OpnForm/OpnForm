<?php

namespace App\Models;

use App\Integrations\OAuth\OAuthProviderService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OAuthProvider extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'oauth_providers';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    // Providers such as One Tap do not issue refresh tokens. Existing links
    // retain their loaded token; only new models receive this storage default.
    protected $attributes = ['refresh_token' => ''];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts()
    {
        return [
            'provider' => OAuthProviderService::class,
            'token_expires_at' => 'datetime',
            'scopes' => 'array'
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
