<?php

namespace App\Enterprise\Oidc\Models;

use App\Models\User;
use Database\Factories\UserIdentityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIdentity extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return UserIdentityFactory::new();
    }

    protected $fillable = [
        'user_id',
        'connection_id',
        'subject',
        'email',
        'claims',
    ];

    protected function casts(): array
    {
        return [
            'claims' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IdentityConnection::class, 'connection_id');
    }
}
