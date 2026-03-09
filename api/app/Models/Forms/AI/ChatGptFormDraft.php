<?php

namespace App\Models\Forms\AI;

use Illuminate\Database\Eloquent\Model;

class ChatGptFormDraft extends Model
{
    protected $table = 'chatgpt_form_drafts';

    protected $fillable = [
        'gpt_chat_id',
        'form_state',
        'draft_version',
        'expires_at',
        'last_accessed_at',
        'handed_off_at',
    ];

    protected function casts()
    {
        return [
            'form_state' => 'array',
            'expires_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'handed_off_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
