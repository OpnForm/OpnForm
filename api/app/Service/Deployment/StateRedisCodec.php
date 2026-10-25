<?php

namespace App\Service\Deployment;

use Illuminate\Cache\RedisStore;
use RuntimeException;

class StateRedisCodec extends RedisStore
{
    public function packValue(mixed $value): string
    {
        $client = $this->connection()->client();
        if ($client->getOption(\Redis::OPT_SERIALIZER) !== \Redis::SERIALIZER_NONE
            || $client->getOption(\Redis::OPT_COMPRESSION) !== \Redis::COMPRESSION_NONE) {
            throw new RuntimeException('State import requires the qualified PHP serializer/compression configuration');
        }

        return (string) $this->connectionAwareSerialize($value, $this->connection());
    }
}
