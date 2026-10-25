<?php

namespace App\Service\Deployment;

use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Providers\Storage\Illuminate;

/** Use the locked package codec/tags on the isolated auth logical database. */
class UnifiedRedisJwtStorage extends Illuminate
{
    public function __construct()
    {
        parent::__construct(Cache::store('auth'));
    }
}
