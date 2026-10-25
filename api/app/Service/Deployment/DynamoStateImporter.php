<?php

namespace App\Service\Deployment;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/** Bounded in-memory snapshot only. No AWS reader, signing or activation. */
class DynamoStateImporter
{
    private const APPLY = <<<'LUA'
local journalDb = tonumber(ARGV[8]); local targetDb = tonumber(ARGV[7])
if redis.call('HGET', KEYS[3], 'manifest') ~= ARGV[1] then return -5 end
if redis.call('HGET', KEYS[3], 'phase') ~= 'importing' then return -6 end
if redis.call('HGET', KEYS[3], 'dirty') ~= '0' then return -8 end
if redis.call('HLEN', KEYS[2]) ~= tonumber(redis.call('HGET', KEYS[3], 'complete')) then
 redis.call('HSET', KEYS[3], 'dirty', '1'); return -9
end
local prior = redis.call('HGET', KEYS[2], ARGV[2])
local now = redis.call('TIME'); local nowms = now[1] * 1000 + math.floor(now[2] / 1000)
local expiration = tonumber(ARGV[4])
if prior then
 if string.sub(prior, 1, 64) ~= ARGV[3] then return -7 end
 if string.sub(prior, 66) ~= 'written' or expiration <= nowms then return 2 end
 redis.call('SELECT', targetDb)
 local unchanged = redis.call('GET', KEYS[1]) == ARGV[5] and redis.call('PEXPIRETIME', KEYS[1]) == expiration
 redis.call('SELECT', journalDb)
 if not unchanged then redis.call('HSET', KEYS[3], 'dirty', '1'); return -2 end
 return 2
end
if expiration <= nowms then
 redis.call('HSET', KEYS[2], ARGV[2], ARGV[3] .. ':expired')
 redis.call('HINCRBY', KEYS[3], 'complete', 1); redis.call('HINCRBY', KEYS[3], 'expired', 1); return 0
end
redis.call('SELECT', targetDb)
local exists = redis.call('EXISTS', KEYS[1])
if exists == 1 then
 redis.call('SELECT', journalDb); redis.call('HSET', KEYS[3], 'dirty', '1'); return -1
end
redis.call('SET', KEYS[1], ARGV[5], 'PXAT', expiration)
if ARGV[6] ~= '' then redis.call('ZADD', KEYS[4], math.floor(expiration / 1000), ARGV[6]) end
redis.call('SELECT', journalDb)
redis.call('HSET', KEYS[2], ARGV[2], ARGV[3] .. ':written')
redis.call('HINCRBY', KEYS[3], 'complete', 1); redis.call('HINCRBY', KEYS[3], 'imported', 1)
return 1
LUA;

    public function begin(array $snapshot): array
    {
        return $this->process($snapshot, null, true);
    }

    public function import(array $snapshot, ?callable $afterCommit = null): array
    {
        return $this->process($snapshot, $afterCommit, false);
    }

    private function process(array $snapshot, ?callable $afterCommit, bool $initialize): array
    {
        $rows = $this->validatedRows($snapshot);
        $servers = app(RedisStateTopology::class)->assertReady();
        $store = Cache::store('redis')->getStore();
        if ($store->connection() !== app('redis')->connection('state')) {
            throw new RuntimeException('State import must use the dedicated durable state connection');
        }
        $auth = Cache::store('auth')->getStore();
        $authDb = $auth->connection()->client()->getDBNum();
        $stateDb = $store->connection()->client()->getDBNum();
        $connection = $store->connection();
        $manifest = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
        [$epoch, $ledger] = $this->keys();
        $targets = [];
        // Validate all mappings/encodings before the first data write.
        foreach ($rows as $index => $row) {
            $rowConnection = match ($row['kind']) {
                'session', 'jwt-revocation' => 'auth',
                'cache' => 'cache',
                default => 'state',
            };
            $rowStore = $rowConnection === 'auth' ? $auth
                : ($rowConnection === 'cache' ? Cache::store('volatile')->getStore() : $store);
            $codec = new StateRedisCodec($rowStore->getRedis(), $rowStore->getPrefix(), $rowConnection);
            $logical = $row['kind'] === 'jwt-revocation'
                ? Cache::store('auth')->tags('tymon.jwt')->taggedItemKey($row['key']) : $row['key'];
            $physical = $rowStore->getPrefix().$logical;
            if (isset($targets[$physical])) {
                throw new RuntimeException('Source records collide in the target namespace');
            }
            $targets[$physical] = [
                $row, $codec->packValue($row['value']),
                hash('sha256', json_encode($snapshot['rows'][$index], JSON_THROW_ON_ERROR)), $logical,
                $rowStore->connection()->client()->getDBNum(),
            ];
        }
        $result = $connection->eval(<<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 0 then
 if ARGV[4] ~= '1' or redis.call('DBSIZE') ~= 0 then return -4 end
 redis.call('SELECT', tonumber(ARGV[5])); local authSize = redis.call('DBSIZE'); redis.call('SELECT', tonumber(ARGV[6]))
 if authSize ~= 0 then return -4 end
 redis.call('HSET', KEYS[1], 'manifest', ARGV[1], 'phase', 'importing', 'run_id', ARGV[2],
 'total', ARGV[3], 'complete', 0, 'imported', 0, 'expired', 0, 'dirty', 0); return 1
end
if ARGV[4] == '1' then return -4 end
if redis.call('HGET', KEYS[1], 'manifest') ~= ARGV[1] then return -1 end
if redis.call('HGET', KEYS[1], 'run_id') ~= ARGV[2] then return -3 end
if redis.call('HGET', KEYS[1], 'phase') ~= 'importing' then return -2 end
return 0
LUA, 1, $epoch, $manifest, $servers['state'], count($rows), $initialize ? '1' : '0', $authDb, $stateDb);
        if ($result < 0) {
            throw new RuntimeException('State import epoch is sealed, admitted or bound to another manifest');
        }
        if ($initialize) {
            return $connection->hgetall($epoch);
        }
        $index = 0;
        foreach ($targets as $key => [$row, $value, $rowHash, $logical, $targetDb]) {
            $result = $connection->eval(
                self::APPLY,
                4,
                $key,
                $ledger,
                $epoch,
                $auth->getPrefix().'tag:tymon.jwt:entries',
                $manifest,
                hash('sha256', $key),
                $rowHash,
                $row['expires_at'] * 1000,
                $value,
                $row['kind'] === 'jwt-revocation' ? $logical : '',
                $targetDb,
                $stateDb
            );
            if ($result < 0) {
                throw new RuntimeException('Import stopped on changed, consumed or conflicting target state');
            }
            if ($afterCommit) {
                $afterCommit(++$index, $result);
            }
        }

        return $connection->hgetall($epoch);
    }

    public function seal(array $snapshot): array
    {
        $this->import($snapshot);
        [$epoch] = $this->keys();
        $connection = app('redis')->connection('state');
        $result = $connection->eval(<<<'LUA'
if redis.call('HGET', KEYS[1], 'phase') ~= 'importing' then return 0 end
if redis.call('HGET', KEYS[1], 'dirty') ~= '0' then return 0 end
if redis.call('HGET', KEYS[1], 'complete') ~= redis.call('HGET', KEYS[1], 'total') then return 0 end
redis.call('HSET', KEYS[1], 'phase', 'sealed'); return 1
LUA, 1, $epoch);
        if ($result !== 1) {
            throw new RuntimeException('Incomplete or dirty import cannot be sealed');
        }

        return $connection->hgetall($epoch);
    }

    public function keys(): array
    {
        $prefix = Cache::store('redis')->getStore()->getPrefix();
        $namespace = config('hosted-state.namespace');
        if (! is_string($namespace) || $namespace === '') {
            throw new RuntimeException('An explicit product state namespace is required');
        }
        $base = $prefix.'opnform:state-import:'.hash('sha256', $namespace);

        return [$base.':epoch', $base.':ledger'];
    }

    private function validatedRows(array $snapshot): array
    {
        if (($snapshot['schema'] ?? null) !== 'opnform-dynamodb-state-snapshot/v1'
            || ($snapshot['namespace'] ?? null) !== config('hosted-state.namespace')
            || ($snapshot['target_configuration_sha256'] ?? null) !== app(HostedStateGuard::class)->configurationFingerprint()
            || ! is_int($snapshot['source_fenced_at'] ?? null) || $snapshot['source_fenced_at'] <= 0
            || $snapshot['source_fenced_at'] > time()
            || ($snapshot['package']['laravel'] ?? null) !== InstalledVersions::getVersion('laravel/framework')
            || ($snapshot['package']['php_serialize'] ?? null) !== PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION
            || ($snapshot['package']['composer_lock_sha256'] ?? null) !== hash_file('sha256', base_path('composer.lock'))
            || ! is_string($snapshot['source_prefix'] ?? null) || $snapshot['source_prefix'] === ''
            || ! is_array($snapshot['rows'] ?? null) || count($snapshot['rows']) > 10000) {
            throw new RuntimeException('State snapshot package, namespace, fence or bounded schema is incompatible');
        }
        $attributes = $snapshot['attributes'] ?? [];
        foreach (['key', 'value', 'expiration'] as $attribute) {
            if (! is_string($attributes[$attribute] ?? null)
                || ! preg_match('/^[a-zA-Z0-9_]+$/', $attributes[$attribute])) {
                throw new RuntimeException('Explicit source attribute mapping is required');
            }
        }

        return array_map(fn (array $row) => app(DynamoStateCodec::class)->decode(
            $row,
            $snapshot['source_prefix'],
            $attributes
        ), $snapshot['rows']);
    }
}
