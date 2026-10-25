<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminApiAction extends Model
{
    public static function contentHash(string $operation, array $input): string
    {
        foreach (['user_id', 'subscription_id', 'expected_amount'] as $field) {
            if (isset($input[$field])) {
                $input[$field] = (int) $input[$field];
            }
        }
        ksort($input);
        return hash('sha256', json_encode([$operation, $input], JSON_THROW_ON_ERROR));
    }

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['request_hash', 'idempotency_hash'];
    protected $casts = ['actor_id' => 'integer', 'token_id' => 'integer', 'result' => 'array'];
}
