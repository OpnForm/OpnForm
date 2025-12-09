<?php

namespace App\Enterprise\Oidc\Models;

use App\Models\Workspace;
use Database\Factories\IdentityConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdentityConnection extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return IdentityConnectionFactory::new();
    }

    public const TYPE_OIDC = 'oidc';
    public const TYPE_SAML = 'saml';

    public const TYPES = [
        self::TYPE_OIDC,
        self::TYPE_SAML,
    ];

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'domain',
        'type',
        'issuer',
        'client_id',
        'client_secret',
        'scopes',
        'options',
        'redirect_path',
        'enabled',
    ];

    protected $hidden = [
        'client_secret',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'options' => 'array',
            'enabled' => 'boolean',
            'client_secret' => 'encrypted',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function userIdentities(): HasMany
    {
        return $this->hasMany(UserIdentity::class, 'connection_id');
    }

    public function getRedirectUrlAttribute(): string
    {
        if ($this->redirect_path) {
            return $this->redirect_path;
        }

        return front_url("/auth/{$this->slug}/callback");
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeForWorkspace($query, ?int $workspaceId)
    {
        return $query->where(function ($q) use ($workspaceId) {
            $q->whereNull('workspace_id')
                ->orWhere('workspace_id', $workspaceId);
        });
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('workspace_id');
    }
}
