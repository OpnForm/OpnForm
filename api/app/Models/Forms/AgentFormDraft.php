<?php

namespace App\Models\Forms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentFormDraft extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLAIMED = 'claimed';

    protected $fillable = [
        'token_hash',
        'definition',
        'schema_version',
        'version',
        'status',
        'claimed_form_id',
        'claim_receipt_hash',
        'claimed_at',
        'editor_session_hash',
        'editor_session_expires_at',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
        'claim_receipt_hash',
        'editor_session_hash',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'schema_version' => 'integer',
            'version' => 'integer',
            'claimed_form_id' => 'integer',
            'claimed_at' => 'datetime',
            'editor_session_expires_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('expires_at', '>', now());
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->expires_at->isFuture();
    }

    public function editorHandoffs(): HasMany
    {
        return $this->hasMany(AgentFormDraftHandoff::class);
    }
}
