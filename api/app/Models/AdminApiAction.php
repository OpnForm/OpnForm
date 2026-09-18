<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminApiAction extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['payload', 'request_hash', 'idempotency_hash'];
    protected $casts = ['actor_id' => 'integer', 'token_id' => 'integer', 'payload' => 'encrypted:array', 'result' => 'array'];
}
