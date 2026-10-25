<?php

namespace App\Service\Deployment;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

class RedisStateTopology
{
    public function assertReady(): array
    {
        $servers = $databases = $prefixes = [];
        foreach (['default', 'cache', 'auth', 'state'] as $name) {
            $settings = config('database.redis.'.$name);
            if (! is_array($settings) || (empty($settings['url']) && empty($settings['host']))) {
                throw new RuntimeException('Explicit logical Redis bindings are required');
            }
            $connection = app('redis')->connection($name);
            $server = $connection->info('server');
            $servers[$name] = $server['run_id'] ?? $server['Server']['run_id'] ?? null;
            $databases[$name] = $connection->client()->getDBNum();
            $prefixes[$name] = $connection->client()->getOption(\Redis::OPT_PREFIX);
            if (! is_string($servers[$name]) || $servers[$name] === ''
                || ! is_int($databases[$name]) || ! is_string($prefixes[$name]) || $prefixes[$name] === '') {
                throw new RuntimeException('Effective Redis identity, DB or product prefix is unavailable');
            }
        }
        if (count(array_unique($servers)) !== 1 || count(array_unique($databases)) !== 4
            || count(array_unique($prefixes)) !== 1) {
            throw new RuntimeException('One Redis requires four distinct effective DBs and one explicit product prefix');
        }
        $logical = ['default' => 'queues:'];
        foreach (['cache' => 'volatile', 'auth' => 'auth', 'state' => 'redis'] as $connection => $storeName) {
            $store = Cache::store($storeName)->getStore();
            if ($store->connection() !== app('redis')->connection($connection)) {
                throw new RuntimeException('Effective cache/auth/state store connection differs from its role');
            }
            $logical[$connection] = $store->getPrefix();
            if (! is_string($logical[$connection]) || $logical[$connection] === '') {
                throw new RuntimeException('Each Redis role requires its own nonempty key prefix');
            }
        }
        foreach ($logical as $left => $prefix) {
            foreach ($logical as $right => $other) {
                if ($left !== $right && (str_starts_with($prefix, $other) || str_starts_with($other, $prefix))) {
                    throw new RuntimeException('Redis logical role prefixes overlap');
                }
            }
        }
        $state = app('redis')->connection('state');
        $policy = $state->command('config', ['GET', 'maxmemory-policy']);
        $sync = $state->command('config', ['GET', 'appendfsync']);
        $limit = $state->command('config', ['GET', 'maxmemory']);
        $persistence = $state->info('persistence');
        $persistence = $persistence['Persistence'] ?? $persistence;
        $memory = $state->info('memory');
        $memory = $memory['Memory'] ?? $memory;
        if (($policy['maxmemory-policy'] ?? null) !== 'noeviction'
            || ($sync['appendfsync'] ?? null) !== 'always'
            || (int) ($limit['maxmemory'] ?? 0) <= 0
            || (int) ($memory['used_memory'] ?? PHP_INT_MAX) >= (int) ($limit['maxmemory'] ?? 0)
            || ($persistence['aof_enabled'] ?? null) !== 1
            || ($persistence['aof_last_write_status'] ?? null) !== 'ok') {
            throw new RuntimeException('Shared Redis requires AOF/always/noeviction and available bounded memory');
        }

        return $servers;
    }
}
